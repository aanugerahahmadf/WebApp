<?php

namespace App\Http\Controllers\Api\User\OrderController;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Events\OrderStatusUpdated\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Product\Product;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use App\Services\BriService\BriService;
use App\Services\ChatService\ChatService;
use App\Services\FaceService\FaceService;
use App\Services\MidtransService\MidtransService;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Services\StorageService\StorageService;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Get user's orders with pagination
     */
    public function getOrders(Request $request)
    {
        try {
            $locale = app()->getLocale();
            $query = Order::where('user_id', Auth::id())
                ->with([
                    'package' => function ($q): void {
                        $q->with('vendor:id,store_name');
                    },
                    'product.category',
                    'user:id,full_name',
                ]);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('booking_date', [$request->from_date, $request->to_date]);
            }

            $orders = $query->latest()->paginate($request->get('per_page', 10));
            $products = $orders->items();

            $data = collect($products)->map(function (Order $order) use ($locale) {
                $pkg = $order->package;
                $wfd = $pkg?->vendor;

                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'user_name' => $order->user?->full_name ?? '',
                    'package_id' => $order->package_id,
                    'product_id' => $order->product_id,
                    'title' => $pkg?->trans('name', $locale) ?? $order->product?->trans('name', $locale) ?? __('Pesanan'),
                    'order_number' => $order->order_number,
                    'total_price' => (float) $order->total_price,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status ?? 'unpaid',
                    'booking_date' => $order->booking_date?->format('Y-m-d'),
                    'event_date' => $order->booking_date?->format('Y-m-d'),
                    'notes' => $order->notes,
                    'resource_type' => $order->package_id ? 'package' : 'product',
                    'item' => $pkg ? [
                        'id' => $pkg->id,
                        'name' => $pkg->trans('name', $locale),
                        'price' => (float) $pkg->price,
                        'discount_price' => (float) ($pkg->discount_price ?? 0),
                        'vendor_id' => $pkg->vendor_id,
                        'vendor' => $wfd ? ['id' => $wfd->id, 'store_name' => $wfd->store_name] : null,
                        'image_url' => $pkg->image_url ?? null,
                    ] : ($order->product ? [
                        'id' => $order->product->id,
                        'name' => $order->product->trans('name', $locale),
                        'price' => (float) $order->product->price,
                        'discount_price' => (float) ($order->product->discount_price ?? 0),
                        'category' => $order->product->category?->name,
                        'image_url' => $order->product->image_url ?? null,
                    ] : null),
                    'package' => $pkg ? [
                        'id' => $pkg->id,
                        'name' => $pkg->trans('name', $locale),
                        'price' => (float) $pkg->price,
                        'vendor_id' => $pkg->vendor_id,
                        'vendor' => $wfd ? ['id' => $wfd->id, 'store_name' => $wfd->store_name] : null,
                    ] : null,
                    'product' => $order->product ? [
                        'id' => $order->product->id,
                        'name' => $order->product->trans('name', $locale),
                        'price' => (float) $order->product->price,
                        'discount_price' => (float) ($order->product->discount_price ?? 0),
                        'category' => $order->product->category?->name,
                    ] : null,
                ];
            })->all();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'has_more_pages' => $orders->hasMorePages(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memuat pesanan'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Create a new order — without Midtrans, returns payment methods directly
     */
    public function createOrder(Request $request)
    {
        try {
            $validatedData = $request->validate([
                // Tanpa rule exists: biarkan findOrFail di bawah menangani
                // package/product tidak ada dengan 404 (bukan 422 validation).
                'package_id' => 'nullable|integer',
                'product_id' => 'nullable|integer',
                'event_date' => 'required|date|after_or_equal:today',
                'event_time' => 'nullable|string',
                'quantity' => 'nullable|integer|min:1',
                'notes' => 'nullable|string|max:1000',
                'customer_name' => 'required|string|max:255',
                'whatsapp' => 'required|string|max:20',
                'voucher_code' => 'nullable|string|max:50',
            ]);

            if (! filled($validatedData['package_id'] ?? null) && ! filled($validatedData['product_id'] ?? null)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau produk terlebih dahulu'),
                ], 422);
            }

            $package = null;
            $product = null;
            if (filled($validatedData['package_id'] ?? null)) {
                $package = Package::with('category')->findOrFail($validatedData['package_id']);
            } else {
                $product = Product::with('category')->findOrFail($validatedData['product_id']);
            }

            // 1. Atomic stock decrement
            $quantity = (int) ($validatedData['quantity'] ?? 1);
            if ($package) {
                $affected = DB::table('packages')
                    ->where('id', $package->id)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);
            } else {
                $affected = DB::table('products')
                    ->where('id', $product->id)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);
            }
            if (! $affected) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Stok tidak mencukupi'),
                ], 400);
            }

            // 3. Calculate prices with voucher
            $itemPrice = (float) ($package?->final_price ?? $product?->final_price ?? $package?->price ?? $product?->price ?? 0);
            $baseTotal = $itemPrice * $quantity;
            $discountAmount = 0;
            $voucherId = null;

            if ($request->filled('voucher_code')) {
                $voucher = Voucher::with('discount')->where('code', $request->voucher_code)
                    ->where('is_active', true)
                    ->where(function ($q): void {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })->first(['*']);

                if ($voucher && $voucher->isValidFor($baseTotal)) {
                    $discountAmount = (int) $voucher->calculateDiscount($baseTotal);
                    $voucherId = $voucher->id;
                }
            }

            $totalPrice = max(0, $baseTotal - $discountAmount);

            $user = Auth::user();

            // 4. Update user WhatsApp if changed
            $wa = $validatedData['whatsapp'];
            if ($user->whatsapp !== $wa) {
                $user->update(['whatsapp' => $wa]);
            }

            // 5. Create Order
            $notes = $validatedData['notes'] ?? '';
            if (! empty($validatedData['event_time'])) {
                $notes = 'Waktu: '.$validatedData['event_time'].($notes ? ' | '.$notes : '');
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'package_id' => $package?->id,
                'product_id' => $product?->id,
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                'total_price' => $totalPrice,
                'quantity' => $quantity,
                'status' => OrderStatus::PENDING,
                'payment_status' => OrderPaymentStatus::UNPAID,
                'booking_date' => $validatedData['event_date'],
                'notes' => $notes,
            ]);

            // 6. Link voucher via user_vouchers pivot
            if ($voucherId) {
                $voucher = Voucher::find($voucherId);
                if ($voucher) {
                    $voucher->assignToUser(Auth::id());
                    $voucher->users()->updateExistingPivot(Auth::id(), [
                        'order_id' => $order->id,
                    ]);
                }
            }

            // 7. Create Transaction (no Midtrans)
            $reference = 'TRX-'.time().'-'.Str::random(4);
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'order_id' => $order->id,
                'type' => 'order',
                'reference_number' => $reference,
                'amount' => $totalPrice,
                'admin_fee' => 0,
                'total_amount' => $totalPrice,
                'status' => 'pending',
                'payment_gateway' => 'manual',
                'notes' => __('Pembayaran Pesanan #').$order->order_number,
            ]);

            // 8. Get active payment methods
            $paymentMethods = $this->paymentMethodsForResponse();

            // 9. Send chat notification to admin
            try {
                $inbox = ChatService::getOrCreateInboxWithAdmin(Auth::id());
                ChatService::sendOrderMessage($inbox, $order->fresh());
            } catch (\Throwable $e) {
                Log::warning('[Order] Failed to send chat notification: '.$e->getMessage());
            }

            $order->load(['package.media', 'product.media']);

            return response()->json([
                'status' => 'success',
                'message' => __('Pesanan berhasil dibuat'),
                'data' => array_merge($order->toArray(), [
                    'transaction_id' => $transaction->id,
                    'transaction' => [
                        'id' => $transaction->id,
                        'reference_number' => $transaction->reference_number,
                        'total_amount' => (float) $transaction->total_amount,
                        'status' => $transaction->status,
                    ],
                    'payment_methods' => $paymentMethods,
                ]),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Item tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] createOrder error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membuat pesanan'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get payment info for an order
     */
    public function getPaymentInfo($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            $transaction = Transaction::where('order_id', $order->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            $paymentMethods = PaymentMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order' => $order,
                    'transaction' => $transaction,
                    'payment_methods' => $paymentMethods,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil info pembayaran'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Buat nomor Virtual Account (BRIVA) untuk pesanan yang dibayar via
     * Virtual Account BRI. Nomor VA unik per transaksi, expire setelah
     * beberapa jam, dan bayaran otomatis masuk ke rekening admin.
     */
    public function createVirtualAccount($id)
    {
        try {
            $bri = app(BriService::class);
            if (! $bri->snapEnabled()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Virtual Account BRI belum diaktifkan. Hubungi admin.'),
                ], 400);
            }

            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar'),
                ], 400);
            }

            $transaction = Transaction::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->first();

            if (! $transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tidak ada transaksi aktif'),
                ], 400);
            }

            // VA sudah pernah dibuat? Kirim ulang tanpa panggil BRI lagi.
            if ($transaction->virtual_account_no) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('Nomor Virtual Account berhasil dibuat'),
                    'data' => [
                        'virtual_account_no' => $transaction->virtual_account_no,
                        'virtual_account_expiry' => $transaction->virtual_account_expiry?->format('Y-m-d\TH:i:sP'),
                        'account_number' => config('bri.account_number'),
                        'account_holder' => config('bri.account_holder'),
                    ],
                ]);
            }

            $va = $bri->createVirtualAccount($transaction);
            if (! $va) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Gagal membuat Virtual Account. Silakan coba lagi.'),
                    'error' => config('app.debug') ? 'BRI create-va failed' : null,
                ], 502);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Nomor Virtual Account berhasil dibuat'),
                'data' => $va,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] createVirtualAccount error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membuat Virtual Account'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Buat kode QRIS dinamis untuk pesanan (QR aktif untuk 1 transaksi).
     */
    public function createQris($id)
    {
        try {
            $bri = app(BriService::class);
            if (! $bri->snapEnabled() || ! config('bri.qr_enabled', false)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('QRIS BRI belum diaktifkan. Hubungi admin.'),
                ], 400);
            }

            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar'),
                ], 400);
            }

            $transaction = Transaction::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->first();

            if (! $transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tidak ada transaksi aktif'),
                ], 400);
            }

            // QRIS sudah pernah dibuat? Kirim ulang dari metadata tanpa panggil BRI lagi.
            $existing = ($transaction->metadata ?? [])['bri_qris']['qr_content'] ?? null;
            if ($existing) {
                $meta = ($transaction->metadata ?? [])['bri_qris'] ?? [];

                return response()->json([
                    'status' => 'success',
                    'message' => __('Kode QRIS berhasil dibuat'),
                    'data' => [
                        'qr_content' => $existing,
                        'qr_expiry' => $meta['payload']['additionalInfo']['validityPeriod']
                            ?? $meta['validity_period'] ?? null,
                    ],
                ]);
            }

            $qr = $bri->createQris($transaction);
            if (! $qr) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Gagal membuat QRIS. Silakan coba lagi.'),
                    'error' => config('app.debug') ? 'BRI qr-mpm-generate failed' : null,
                ], 502);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Kode QRIS berhasil dibuat'),
                'data' => $qr,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] createQris error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membuat QRIS'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Send payment confirmation notifications via all channels
     */
    public function sendPaymentNotifications(Order $order): void
    {
        try {
            $user = $order->user;
            $orderNumber = $order->order_number;
            $itemName = $order->package?->name ?? $order->product?->name ?? 'Pesanan';
            $total = 'Rp '.number_format((float) $order->total_price, 0, ',', '.');
            $appName = config('app.name', 'Aplikasi');

            // 1. Database notification (bell icon)
            [$dbTitle, $dbBody] = PlatformNotificationService::withRecipientLocale(
                $user,
                fn () => [__('Pembayaran Berhasil'), __('Pesanan #:order telah dibayar sebesar :total.', ['order' => $orderNumber, 'total' => $total])]
            );
            PlatformNotificationService::send($user, $dbTitle, $dbBody);

            // 2. WhatsApp via Fonnte
            try {
                $phone = $this->normalizePhone($user->whatsapp ?? $user->phone ?? '');
                $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
                if (! empty($phone) && ! empty($token)) {
                    $waMessage = PlatformNotificationService::withRecipientLocale(
                        $user,
                        fn () => __('whatsapp.payment_success', [
                            'name' => $user->full_name,
                            'order' => $orderNumber,
                            'item' => $itemName,
                            'total' => $total,
                            'app' => $appName,
                        ])
                    );

                    Http::withHeaders(['Authorization' => $token])
                        ->timeout(10)
                        ->post('https://api.fonnte.com/send', [
                            'target' => $phone,
                            'message' => $waMessage,
                        ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[Payment] WhatsApp notification failed: '.$e->getMessage());
            }

            // 3. Email with invoice
            try {
                if (! empty($user->email)) {
                    $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();
                    $dompdf = new Dompdf;
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();

                    Mail::send([], [], function ($message) use ($user, $order, $dompdf, $total, $appName) {
                        $message->to($user->email)
                            ->subject(__('Pembayaran Berhasil - :app', ['app' => $appName]))
                            ->html(__('Halo :name,<br><br>Pembayaran Anda untuk pesanan <b>:order</b> sebesar <b>:total</b> telah berhasil.<br><br>Terima kasih telah melakukan pembayaran. Pesanan Anda akan segera diproses.<br><br>Invoice terlampir dalam PDF.', [
                                'name' => $user->full_name,
                                'order' => $order->order_number,
                                'total' => $total,
                            ]))
                            ->attachData($dompdf->output(), 'invoice-'.$order->order_number.'.pdf', ['mime' => 'application/pdf']);
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('[Payment] Email notification failed: '.$e->getMessage());
            }

            // 4. In-app chat message
            try {
                $inbox = ChatService::getOrCreateInboxWithAdmin($user->id);
                $admin = User::whereHas('roles', function ($q) {
                    $q->where('name', 'super_admin');
                })->first();
                $type = $order->package_id ? 'package' : 'product';
                $item = $order->package ?? $order->product;
                $itemName2 = $item?->name ?? $itemName;

                Message::create([
                    'inbox_id' => $inbox->id,
                    'user_id' => $admin ? $admin->id : $user->id,
                    'message' => __('✅ Pembayaran untuk pesanan #:order sebesar :total telah berhasil dikonfirmasi.', [
                        'order' => $order->order_number,
                        'total' => $total,
                    ]),
                    'meta' => [
                        'type' => $type,
                        'id' => $item?->id,
                        'name' => $itemName2,
                        'price' => $order->total_price,
                        'image' => $item?->image_url,
                        'is_order' => true,
                        'is_payment_update' => true,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->status,
                        'payment_status' => __('LUNAS'),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[Payment] Chat notification failed: '.$e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('[Payment] sendPaymentNotifications failed: '.$e->getMessage());
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Send notification that a manual payment is awaiting admin verification
     */
    private function sendPendingPaymentNotifications(Order $order): void
    {
        try {
            $user = $order->user;
            $orderNumber = $order->order_number;
            $itemName = $order->package?->name ?? $order->product?->name ?? 'Pesanan';
            $total = 'Rp '.number_format((float) $order->total_price, 0, ',', '.');
            $appName = config('app.name', 'Aplikasi');

            // 1. Database notification for user (bell icon)
            [$dbTitle, $dbBody] = PlatformNotificationService::withRecipientLocale(
                $user,
                fn () => [__('Konfirmasi Pembayaran Diterima'), __('Pembayaran untuk pesanan #:order sebesar :total sedang menunggu verifikasi admin.', ['order' => $orderNumber, 'total' => $total])]
            );
            PlatformNotificationService::send($user, $dbTitle, $dbBody);

            // 2. WhatsApp via Fonnte
            try {
                $phone = $this->normalizePhone($user->whatsapp ?? $user->phone ?? '');
                $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
                if (! empty($phone) && ! empty($token)) {
                    $waMessage = PlatformNotificationService::withRecipientLocale(
                        $user,
                        fn () => __('whatsapp.payment_pending', [
                            'name' => $user->full_name,
                            'order' => $orderNumber,
                            'item' => $itemName,
                            'total' => $total,
                            'app' => $appName,
                        ])
                    );

                    Http::withHeaders(['Authorization' => $token])
                        ->timeout(10)
                        ->post('https://api.fonnte.com/send', [
                            'target' => $phone,
                            'message' => $waMessage,
                        ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[Payment] Pending WhatsApp notification failed: '.$e->getMessage());
            }

            // 3. In-app chat to admin so they can verify
            try {
                $inbox = ChatService::getOrCreateInboxWithAdmin($user->id);
                $admin = User::whereHas('roles', function ($q) {
                    $q->where('name', 'super_admin');
                })->first();
                $type = $order->package_id ? 'package' : 'product';
                $item = $order->package ?? $order->product;

                Message::create([
                    'inbox_id' => $inbox->id,
                    'user_id' => $admin ? $admin->id : $user->id,
                    'message' => __('⚠️ User mengkonfirmasi pembayaran untuk pesanan #:order sebesar :total. Mohon verifikasi pembayaran.', [
                        'order' => $order->order_number,
                        'total' => $total,
                    ]),
                    'meta' => [
                        'type' => $type,
                        'id' => $item?->id,
                        'name' => $item?->name ?? $itemName,
                        'price' => $order->total_price,
                        'image' => $item?->image_url,
                        'is_order' => true,
                        'is_payment_update' => true,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->status,
                        'payment_status' => __('MENUNGGU VERIFIKASI'),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[Payment] Pending chat notification failed: '.$e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('[Payment] sendPendingPaymentNotifications failed: '.$e->getMessage());
        }
    }

    /**
     * Confirm payment manually (user has transferred)
     */
    public function confirmPayment($id, Request $request)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if ($order->status === OrderStatus::CANCELLED) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibatalkan'),
                ], 400);
            }

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar'),
                ], 400);
            }

            $validatedData = $request->validate([
                'payment_method_id' => 'nullable|exists:payment_methods,id',
                'payment_method' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:100',
                'account_holder' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'card_number' => 'nullable|string|max:19',
                'card_holder' => 'nullable|string|max:255',
                'card_expiry' => 'nullable|string|max:7',
                'card_cvv' => 'nullable|string|max:4',
            ]);

            // Detect credit card payment method
            $isCreditCard = false;
            if (! empty($validatedData['payment_method_id'])) {
                $pm = PaymentMethod::find($validatedData['payment_method_id']);
                $isCreditCard = $pm !== null && $pm->type === 'credit_card';
            }

            if ($isCreditCard) {
                $requiredCard = ['card_number', 'card_holder', 'card_expiry', 'card_cvv'];
                foreach ($requiredCard as $field) {
                    if (empty($validatedData[$field])) {
                        return response()->json([
                            'status' => 'error',
                            'message' => __('Data kartu tidak lengkap'),
                            'errors' => [$field => [__('Kolom wajib diisi')]],
                        ], 422);
                    }
                }
            }

            // Update transaction with payment details
            $transaction = Transaction::where('order_id', $order->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (! $transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tidak ada transaksi aktif'),
                ], 400);
            }

            $metadata = $transaction->metadata ?? [];
            if ($isCreditCard) {
                $metadata['card'] = [
                    'card_number' => '****'.substr(preg_replace('/\D/', '', (string) $validatedData['card_number']), -4),
                    'card_holder' => $validatedData['card_holder'],
                    'card_expiry' => $validatedData['card_expiry'],
                ];
            }

            $transaction->update([
                'payment_method_id' => $validatedData['payment_method_id'] ?? null,
                'payment_method' => $validatedData['payment_method'] ?? $transaction->payment_method,
                'status' => $isCreditCard ? 'success' : 'processing',
                'paid_at' => $isCreditCard ? now() : null,
                'metadata' => $metadata,
                'notes' => $isCreditCard
                    ? __('Pembayaran oleh pengguna: ').($validatedData['account_holder'] ?? $order->user->full_name)
                    : __('User mengkonfirmasi pembayaran manual, menunggu verifikasi admin'),
            ]);

            if ($isCreditCard) {
                $order->update([
                    'payment_status' => OrderPaymentStatus::PAID,
                ]);

                event(new OrderStatusUpdated([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value ?? $order->status,
                    'payment_status' => OrderPaymentStatus::PAID->value,
                    'updated_at' => now()->toISOString(),
                ], $order->user_id));

                $this->sendPaymentNotifications($order);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Pembayaran berhasil'),
                    'data' => [
                        'order' => $order->fresh(),
                        'transaction' => $transaction->fresh(),
                    ],
                ]);
            }

            // Manual payment (bank transfer / QRIS / e-wallet / cash):
            // uang sudah ditransfer user, tapi status LUNAS hanya setelah diverifikasi admin.
            $order->update([
                'payment_status' => OrderPaymentStatus::PENDING,
            ]);

            event(new OrderStatusUpdated([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value ?? $order->status,
                'payment_status' => OrderPaymentStatus::PENDING->value,
                'updated_at' => now()->toISOString(),
            ], $order->user_id));

            $this->sendPendingPaymentNotifications($order);

            return response()->json([
                'status' => 'success',
                'message' => __('Konfirmasi pembayaran diterima. Menunggu verifikasi admin.'),
                'data' => [
                    'order' => $order->fresh(),
                    'transaction' => $transaction->fresh(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengkonfirmasi pembayaran'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Upload payment proof (auto-confirm)
     */
    public function uploadProof($id, Request $request, FaceService $faceService)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar'),
                ], 400);
            }

            // Bukti pembayaran WAJIB disertakan — satu ATAU banyak berkas
            // (foto, video, atau dokumen seperti PDF).
            $request->validate([
                'proof_images' => 'sometimes|array|min:1',
                'proof_images.*' => 'file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp,pdf|max:51200',
                'proof_image' => 'sometimes|file|mimes:jpeg,png,jpg,mp4,mov,m4v,webm,3gp,pdf|max:51200',
            ]);

            $files = $request->hasFile('proof_images')
                ? $request->file('proof_images')
                : ($request->hasFile('proof_image') ? [$request->file('proof_image')] : []);

            if (empty($files)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Bukti pembayaran wajib disertakan'),
                ], 422);
            }

            $transaction = Transaction::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->first();

            $expected = 0.0;
            if ($transaction) {
                $expected = (float) (($transaction->total_amount ?? 0) > 0 ? $transaction->total_amount : ($transaction->amount ?? 0));
            }

            $storedProofs = [];
            $verifiedAny = false;
            $aiVerified = null;
            $aiFallback = [];

            foreach ($files as $index => $file) {
                $fileNameBase = 'proof_'.$order->id.'_'.time().'_'.$index;
                $realPath = $file->getRealPath();
                $ext = strtolower($file->getClientOriginalExtension());
                $isVideo = $faceService->isVideoFile($realPath);
                $isScannable = in_array($ext, ['jpeg', 'jpg', 'png', 'mp4', 'mov', 'm4v', 'webm', '3gp'], true);

                // Verifikasi tiap berkas gambar/video via AI Core
                // (OCR nominal + Computer Vision; frame video diekstrak server-side).
                $ai = [];
                if ($transaction && $isScannable) {
                    $ai = $faceService->verifyProof($realPath, $expected > 0 ? $expected : null);
                }

                $isVerified = ($ai['success'] ?? false) && ($ai['verified'] ?? false);
                if ($isVerified) {
                    $verifiedAny = true;
                    $aiVerified = $ai;
                } elseif ($aiFallback === []) {
                    $aiFallback = $ai;
                }

                // Simpan: video memakai frame hasil AI bila tersedia (fallback video asli).
                if ($isVideo) {
                    $path = $faceService->storeVideoFrame($ai['frame_base64'] ?? null, 'payment-proofs', $fileNameBase.'.jpg')
                        ?? StorageService::uploadWithCustomName($file, 'payment-proofs', $fileNameBase.'.'.$ext);
                } else {
                    $path = StorageService::uploadWithCustomName($file, 'payment-proofs', $fileNameBase.'.'.$ext);
                }

                $storedProofs[] = [
                    'path' => $path,
                    'url' => url(StorageService::url($path)),
                    'type' => $isVideo ? 'video' : (in_array($ext, ['pdf'], true) ? 'document' : 'image'),
                    'verified' => $isVerified,
                    'ai_reason' => $ai['reason'] ?? ($ai['message'] ?? null),
                ];
            }

            $aiBest = $aiVerified ?? $aiFallback;
            $proofUrls = array_map(fn ($p) => $p['url'], $storedProofs);

            if ($transaction) {

                $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
                $metadata['payment_proofs'] = array_map(fn ($p) => $p['path'], $storedProofs);
                $metadata['payment_proof_urls'] = $proofUrls;
                $metadata['proof_ai'] = [
                    'verified' => $verifiedAny,
                    'reason' => $aiBest['reason'] ?? 'AI_UNAVAILABLE',
                    'expected_amount' => $expected > 0 ? $expected : null,
                    'matched_amount' => $aiBest['matched_amount'] ?? null,
                ];
                $transaction->update(['metadata' => $metadata]);
            }

            if ($verifiedAny) {
                $transaction->markAsSuccess();
                $this->sendPaymentNotifications($order);
            }

            return response()->json([
                'status' => 'success',
                'message' => $verifiedAny
                    ? __('Bukti pembayaran terverifikasi, pembayaran berhasil')
                    : ($aiBest['message'] ?? __('Bukti pembayaran disimpan, menunggu verifikasi')),
                'data' => [
                    'order' => $order->fresh(),
                    'transaction' => $transaction?->fresh(),
                    'proof_images_urls' => $proofUrls,
                    'payment_verified' => $verifiedAny,
                    'ai_reason' => $aiBest['reason'] ?? ($aiBest['message'] ?? 'AI_UNAVAILABLE'),
                    'matched_amount' => $aiBest['matched_amount'] ?? null,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan')], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal upload bukti pembayaran'), 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    /**
     * Track a specific order by order number
     */
    public function trackOrder($orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', Auth::id())
                ->with(['package.media', 'product.media'])
                ->firstOrFail(['*']);

            return response()->json([
                'status' => 'success',
                'data' => $order,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan atau bukan milik Anda'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] trackOrder error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal melacak pesanan'),
            ], 500);
        }
    }

    /**
     * Get order details by ID
     */
    public function show($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['package.media', 'product.media', 'latestTransaction'])
                ->firstOrFail(['*']);

            $paymentMethods = $this->paymentMethodsForResponse();

            return response()->json([
                'status' => 'success',
                'data' => array_merge($order->toArray(), [
                    'payment_methods' => $paymentMethods,
                ]),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan atau bukan milik Anda'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] show error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil detail pesanan'),
            ], 500);
        }
    }

    /**
     * Aktifkan tombol "Bayar via Gateway" pada metode yang didukung.
     */
    private function paymentMethodsForResponse(): array
    {
        $midtransEnabled = app(MidtransService::class)->isEnabled();
        $briEnabled = app(BriService::class)->enabled();

        return PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (PaymentMethod $m) use ($midtransEnabled, $briEnabled) {
                $gatewayEnabled = false;
                if ($m->type === 'virtual_account') {
                    $gatewayEnabled = $briEnabled;
                } elseif ($m->type === 'qris') {
                    $gatewayEnabled = $briEnabled && config('bri.qr_enabled', false);
                } else {
                    $gatewayEnabled = $midtransEnabled && in_array($m->type, ['e_wallet', 'credit_card', 'bank_transfer']);
                }

                return array_merge($m->toArray(), [
                    'gateway_enabled' => $gatewayEnabled,
                ]);
            })
            ->all();
    }

    /**
     * Buat pembayaran Midtrans Snap untuk e-wallet / QRIS / kartu / bank.
     */
    public function initiatePayment($id, Request $request)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with('latestTransaction')
                ->firstOrFail();

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar'),
                ], 400);
            }

            $transaction = $order->latestTransaction;
            if (! $transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Tidak ada transaksi aktif'),
                ], 400);
            }

            $pm = $request->input('payment_method_id')
                ? PaymentMethod::find($request->input('payment_method_id'))
                : null;

            $midtrans = app(MidtransService::class);
            if (! $midtrans->isEnabled()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Gateway pembayaran belum diaktifkan. Hubungi admin.'),
                ], 400);
            }

            if ($pm) {
                $transaction->update([
                    'payment_method_id' => $pm->id,
                    'payment_method' => $pm->name,
                ]);
            }

            $result = $midtrans->createSnapToken($transaction, $pm?->type ?? 'e_wallet', $pm?->code);

            if (! $result) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Gagal membuat pembayaran. Silakan coba lagi.'),
                ], 502);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Silakan selesaikan pembayaran di halaman Midtrans.'),
                'data' => [
                    'snap_token' => $result['token'],
                    'redirect_url' => $result['redirect_url'],
                    'transaction' => $transaction->fresh(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membuat pembayaran'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if (in_array($order->status, [OrderStatus::COMPLETED, OrderStatus::CANCELLED])) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dalam status: ').($order->status instanceof OrderStatus ? $order->status->getLabel() : (string) $order->status),
                ], 400);
            }

            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            event(new OrderStatusUpdated([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => 'cancelled',
                'payment_status' => $order->payment_status->value ?? (string) $order->payment_status,
                'updated_at' => now()->toISOString(),
            ], $order->user_id));

            return response()->json([
                'status' => 'success',
                'message' => __('Pesanan berhasil dibatalkan'),
                'data' => $order,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan atau bukan milik Anda'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] cancelOrder error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal membatalkan pesanan'),
            ], 500);
        }
    }

    /**
     * Download invoice PDF
     */
    public function downloadInvoice($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['user', 'package.category', 'package.media', 'product.category', 'product.media', 'latestTransaction'])
                ->firstOrFail();

            $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'invoice-'.$order->order_number.'.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan')], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengunduh invoice'), 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }

    /**
     * Send invoice via email
     */
    public function sendInvoiceEmail($id, Request $request)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['user', 'package.category', 'package.media', 'product.category', 'product.media', 'latestTransaction'])
                ->firstOrFail();

            $email = $request->input('email', $order->user->email);
            $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            Mail::send([], [], function ($message) use ($order, $email, $dompdf) {
                $message->to($email)
                    ->subject(__('Invoice Pesanan #:order', ['order' => $order->order_number]))
                    ->html(__('Halo :name, berikut invoice untuk pesanan #:order Anda.', [
                        'name' => $order->user->full_name,
                        'order' => $order->order_number,
                    ]))
                    ->attachData($dompdf->output(), 'invoice-'.$order->order_number.'.pdf', ['mime' => 'application/pdf']);
            });

            return response()->json([
                'status' => 'success',
                'message' => __('Invoice berhasil dikirim ke :email', ['email' => $email]),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan')], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengirim invoice'), 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }
    }
}
