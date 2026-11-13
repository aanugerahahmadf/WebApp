<?php

namespace App\Http\Controllers\Api\User\OrderController;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Enums\PaymentStatus\PaymentStatus;
use App\Events\OrderStatusUpdated\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use App\Models\Voucher\Voucher;
use App\Support\ShippingTracking\ShippingTracking;
use App\Services\CourierTracking\OrderShippingSyncService;
use App\Services\BriService\BriService;
use App\Services\ChatService\ChatService;
use App\Services\FaceService\FaceService;
use App\Services\MidtransService\MidtransService;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Services\StorageService\StorageService;
use App\Support\ChatStaff\ChatStaff;
use App\Support\PaymentWindow\PaymentWindow;
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
                    'package',
                    'catalog.category',
                    'user:id,full_name',
                    'latestTransaction',
                ]);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('from_date')) {
                $query->whereDate('booking_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('booking_date', '<=', $request->to_date);
            }

            // History payment: metode dibaca dari transaksi aktif order.
            $paymentType = $request->input('payment_type');
            if (in_array($paymentType, ['virtual_account', 'qris'], true)) {
                $query->whereHas('latestTransaction', function ($q) use ($paymentType): void {
                    $q->where('checkout_payment_type', $paymentType);
                });
            }

            $reviewStatus = $request->input('review_status');
            if (in_array($reviewStatus, ['unreviewed', 'reviewed'], true)) {
                $userId = (int) Auth::id();
                $reviewedConstraint = function ($q) use ($userId): void {
                    $q->where(function ($inner) use ($userId): void {
                        $inner->whereNotNull('catalog_id')
                            ->whereHas('catalog.reviews', fn ($r) => $r->where('user_id', $userId));
                    })->orWhere(function ($inner) use ($userId): void {
                        $inner->whereNotNull('package_id')
                            ->whereHas('package.reviews', fn ($r) => $r->where('user_id', $userId));
                    });
                };

                if ($reviewStatus === 'unreviewed') {
                    $query->where('status', OrderStatus::COMPLETED->value)
                        ->whereNot($reviewedConstraint);
                } else {
                    $query->where($reviewedConstraint);
                }
            }

            $query->when(
                in_array($request->input('sort'), ['oldest', 'newest'], true),
                fn ($q): mixed => $q->orderBy('created_at', $request->input('sort') === 'oldest' ? 'asc' : 'desc'),
                fn ($q): mixed => $q->latest()
            );

            $orders = $query->paginate($request->get('per_page', 10));
            $catalogs = $orders->items();

            $reviewIndex = $this->reviewIndexForOrders($catalogs, (int) Auth::id());

            $data = collect($catalogs)->map(function (Order $order) use ($locale, $reviewIndex) {
                $pkg = $order->package;

                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'user_name' => $order->user?->full_name ?? '',
                    'package_id' => $order->package_id,
                    'tier_id' => $order->package_id,
                    'catalog_id' => $order->catalog_id,
                    'title' => $pkg?->trans('name', $locale) ?? $order->catalog?->trans('name', $locale) ?? __('Pesanan'),
                    'order_number' => $order->order_number,
                    'total_price' => (float) $order->total_price,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status ?? 'unpaid',
                    'booking_date' => $order->booking_date?->format('Y-m-d'),
                    'event_date' => $order->booking_date?->format('Y-m-d'),
                    'notes' => $order->notes,
                    'resource_type' => $order->package_id ? 'tier' : 'catalog',
                    'legacy_resource_type' => $order->package_id ? 'package' : 'catalog',
                    'item' => $pkg ? [
                        'id' => $pkg->id,
                        'name' => $pkg->trans('name', $locale),
                        'price' => (float) $pkg->price,
                        'discount_price' => (float) ($pkg->discount_price ?? 0),
                        'final_price' => (float) $pkg->final_price,
                        'image_url' => $pkg->image_url ?? null,
                    ] : ($order->catalog ? [
                        'id' => $order->catalog->id,
                        'name' => $order->catalog->trans('name', $locale),
                        'category' => $order->catalog->category?->name,
                        'image_url' => $order->catalog->image_url ?? null,
                    ] : null),
                    'package' => $pkg ? [
                        'id' => $pkg->id,
                        'name' => $pkg->trans('name', $locale),
                        'price' => (float) $pkg->price,
                        'discount_price' => (float) ($pkg->discount_price ?? 0),
                        'final_price' => (float) $pkg->final_price,
                    ] : null,
                    // Canonical API vocabulary; `package` remains for older clients.
                    'tier' => $pkg ? [
                        'id' => $pkg->id,
                        'tier_id' => $pkg->id,
                        'name' => $pkg->trans('name', $locale),
                        'price' => (float) $pkg->price,
                        'discount_price' => (float) ($pkg->discount_price ?? 0),
                        'final_price' => (float) $pkg->final_price,
                    ] : null,
                    'catalog' => $order->catalog ? [
                        'id' => $order->catalog->id,
                        'name' => $order->catalog->trans('name', $locale),
                        'category' => $order->catalog->category?->name,
                    ] : null,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'booking_time' => $this->eventTimeFor($order),
                    'quantity' => (int) ($order->quantity ?? 1),
                ] + $this->checkoutHistoryFields($order, $reviewIndex[$order->id] ?? null);
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
                'error' => null,
            ], 500);
        }
    }

    /**
     * Create a new order — without Midtrans, returns payment methods directly
     */
    public function createOrder(Request $request)
    {
        try {
            if ($request->filled('tier_id') && ! $request->filled('package_id')) {
                $request->merge(['package_id' => $request->input('tier_id')]);
            }
            $validatedData = $request->validate([
                // Tanpa rule exists: biarkan findOrFail di bawah menangani
                // package/catalog tidak ada dengan 404 (bukan 422 validation).
                'package_id' => 'required|integer',
                'catalog_id' => 'required|integer',
                'event_date' => 'required|date|after_or_equal:today',
                'event_time' => 'nullable|string',
                'quantity' => 'nullable|integer|min:1',
                'notes' => 'nullable|string|max:1000',
                'customer_name' => 'required|string|max:255',
                'whatsapp' => 'required|string|max:20',
                'voucher_code' => 'nullable|string|max:50',
            ]);

            $package = Package::with(['category', 'catalog'])->findOrFail($validatedData['package_id']);
            // A Tier is always scoped to its parent Catalog. Never silently
            // order a Tier from another Catalog.
            if ((int) $package->catalog_id !== (int) $validatedData['catalog_id']) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Paket tidak termasuk dalam katalog yang dipilih'),
                ], 422);
            }
            $catalog = $package->catalog;

            // 1. Atomic stock decrement
            $quantity = (int) ($validatedData['quantity'] ?? 1);
            $affected = DB::table('packages')
                ->where('id', $package->id)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);
            if (! $affected) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Stok tidak mencukupi'),
                ], 400);
            }
            // Catalog stock is a denormalized total of all tier stocks.
            $package?->catalog?->syncStockFromTiers();

            // 3. Calculate prices with voucher
            $itemPrice = (float) ($package?->final_price ?? $package?->price ?? 0);
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
                'catalog_id' => $catalog?->id ?? $validatedData['catalog_id'] ?? null,
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

            $order->load(['package.media', 'catalog.media']);

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
                    'payment' => [
                        'amount' => PaymentWindow::amount($order, $transaction),
                        'deadline' => PaymentWindow::deadline($order, $transaction)?->toIso8601String(),
                        'expiry_hours' => PaymentWindow::expiryHours(),
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
                'error' => null,
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
                    'payment' => [
                        'amount' => PaymentWindow::amount($order, $transaction),
                        'deadline' => PaymentWindow::deadline($order, $transaction)?->toIso8601String(),
                        'expiry_hours' => PaymentWindow::expiryHours(),
                    ],
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
                'error' => null,
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

            $this->tagCheckoutPaymentType($transaction, 'virtual_account');

            // VA sudah pernah dibuat? Kirim ulang tanpa panggil BRI lagi.
            if ($transaction->virtual_account_no
                && (! $transaction->virtual_account_expiry || $transaction->virtual_account_expiry->isFuture())) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('Nomor Virtual Account berhasil dibuat'),
                    'data' => [
                        'virtual_account_no' => $transaction->virtual_account_no,
                        'virtual_account_name' => $transaction->virtual_account_name,
                        'virtual_account_expiry' => $transaction->virtual_account_expiry?->format('Y-m-d\TH:i:sP'),
                        'amount' => PaymentWindow::amount($order, $transaction),
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
                'data' => [
                    'virtual_account_no' => $va['virtual_account_no'],
                    'virtual_account_name' => $va['virtual_account_name'],
                    'virtual_account_expiry' => $va['virtual_account_expiry'],
                    'amount' => PaymentWindow::amount($order, $transaction),
                ],
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
                'error' => null,
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

            $this->tagCheckoutPaymentType($transaction, 'qris');

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
                        'amount' => PaymentWindow::amount($order, $transaction),
                        'deadline' => PaymentWindow::deadline($order, $transaction)?->toIso8601String(),
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
                'data' => array_merge($qr, [
                    'amount' => PaymentWindow::amount($order, $transaction),
                    'deadline' => PaymentWindow::deadline($order, $transaction)?->toIso8601String(),
                ]),
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
                'error' => null,
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
            $itemName = $order->package?->name ?? $order->catalog?->name ?? 'Pesanan';
            $transaction = $order->transactions()->latest()->first();
            $total = PaymentWindow::format(PaymentWindow::amount($order, $transaction));
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
                    $dompdf = \App\Support\PdfLayout\PdfLayout::apply(new Dompdf);
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
                // Identitas staf: baris `users` yang tertaut ke super admin.
                // `User::whereHas('roles', super_admin)` selalu null sejak role
                // admin pindah ke tabel `admins`, jadi kode lama jatuh ke
                // `?: $user->id` -- pesan konfirmasi terkirim seolah dari
                // pembeli sendiri.
                $admin = ChatStaff::adminUser();
                $type = $order->package_id ? 'package' : 'catalog';
                $item = $order->package ?? $order->catalog;
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
            $itemName = $order->package?->name ?? $order->catalog?->name ?? 'Pesanan';
            $transaction = $order->transactions()->latest()->first();
            $total = PaymentWindow::format(PaymentWindow::amount($order, $transaction));
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
                // Identitas staf: baris `users` yang tertaut ke super admin.
                // `User::whereHas('roles', super_admin)` selalu null sejak role
                // admin pindah ke tabel `admins`, jadi kode lama jatuh ke
                // `?: $user->id` -- pesan konfirmasi terkirim seolah dari
                // pembeli sendiri.
                $admin = ChatStaff::adminUser();
                $type = $order->package_id ? 'package' : 'catalog';
                $item = $order->package ?? $order->catalog;

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
                'error' => null,
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
            return response()->json(['status' => 'error', 'message' => __('Gagal upload bukti pembayaran'), 'error' => null], 500);
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
                ->with(['package.media', 'catalog.media', 'latestTransaction', 'timelines'])
                ->firstOrFail(['*']);

            return response()->json([
                'status' => 'success',
                'data' => $this->orderDetailPayload($order),
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
                ->with(['package.media', 'catalog.media', 'latestTransaction', 'timelines'])
                ->firstOrFail(['*']);

            $paymentMethods = $this->paymentMethodsForResponse();

            return response()->json([
                'status' => 'success',
                'data' => array_merge($this->orderDetailPayload($order), [
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
     * Revisi pesanan sebelum dibayar: detail booking dan metode checkout
     * (Virtual Account / QRIS). Order yang sudah lunas hanya menjadi history.
     */
    public function updateOrder(Request $request, $id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if (! $this->canEditOrder($order)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan sudah dibayar atau sedang diproses dan tidak dapat diubah lagi'),
                ], 409);
            }

            $validatedData = $request->validate([
                'event_date' => 'nullable|date|after_or_equal:today',
                'event_time' => 'nullable|string|max:255',
                'quantity' => 'nullable|integer|min:1',
                'notes' => 'nullable|string|max:1000',
                'whatsapp' => 'nullable|string|max:20',
                'voucher_code' => 'nullable|string|max:50',
                'payment_type' => 'nullable|string|in:virtual_account,qris',
            ]);

            $bri = app(BriService::class);
            if ($request->has('payment_type') && $validatedData['payment_type'] !== null) {
                $isVa = $validatedData['payment_type'] === 'virtual_account';
                $enabled = $isVa ? $bri->snapEnabled() : ($bri->snapEnabled() && config('bri.qr_enabled', false));
                if (! $enabled) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $isVa
                            ? __('Virtual Account BRI belum diaktifkan. Hubungi admin.')
                            : __('QRIS BRI belum diaktifkan. Hubungi admin.'),
                    ], 400);
                }
            }

            // 1. Stok disesuaikan selisihnya saja; tier tidak boleh berganti
            //    setelah order dibuat (harga dan slot event sudah terkunci).
            $oldQuantity = (int) ($order->quantity ?? 1);
            $newQuantity = array_key_exists('quantity', $validatedData)
                ? (int) $validatedData['quantity']
                : $oldQuantity;

            if ($newQuantity !== $oldQuantity && $order->package) {
                $delta = $newQuantity - $oldQuantity;
                if ($delta > 0) {
                    $affected = DB::table('packages')
                        ->where('id', $order->package->id)
                        ->where('stock', '>=', $delta)
                        ->decrement('stock', $delta);
                    if (! $affected) {
                        return response()->json([
                            'status' => 'error',
                            'message' => __('Stok tidak mencukupi'),
                        ], 400);
                    }
                } else {
                    DB::table('packages')
                        ->where('id', $order->package->id)
                        ->increment('stock', abs($delta));
                }
                $order->package?->catalog?->syncStockFromTiers();
            }

            // 2. Harga dihitung ulang dengan aturan yang sama seperti createOrder.
            $itemPrice = (float) ($order->package?->final_price ?? $order->package?->price ?? $order->total_price);
            $baseTotal = $itemPrice * $newQuantity;

            $voucherCode = $request->has('voucher_code')
                ? $validatedData['voucher_code']
                : $this->linkedVoucherCode($order);
            $voucher = $this->resolveCheckoutVoucher($voucherCode, $baseTotal);
            $discountAmount = $voucher ? (int) $voucher->calculateDiscount($baseTotal) : 0;
            $totalPrice = max(0, $baseTotal - $discountAmount);

            // 3. Tulis field booking. `event_time` dipindah ke kolom booking_time
            //    dan prefix lama di notes dibuang agar tidak tercatat dua kali.
            $bookingDate = $validatedData['event_date'] ?? null;
            $notes = $request->has('notes')
                ? $this->stripEventTimeNote($validatedData['notes'])
                : $this->stripEventTimeNote($order->notes);
            $eventTime = $request->has('event_time')
                ? $validatedData['event_time']
                : $this->extractEventTimeNote($order->notes);
            $bookingTime = $this->normalizeBookingTime($eventTime);

            // Waktu yang tidak bisa dibaca sebagai jam (mis. "10.00 WIB") tetap
            // disimpan pada notes, mengikuti konvensi createOrder.
            if (! $bookingTime && filled($eventTime)) {
                $notes = 'Waktu: '.$eventTime.($notes ? ' | '.$notes : '');
            }

            $order->update([
                'total_price' => $totalPrice,
                'quantity' => $newQuantity,
                'booking_date' => $bookingDate ?? $order->booking_date,
                'booking_time' => $bookingTime,
                'notes' => $notes,
            ]);

            if ($request->has('whatsapp') && filled($validatedData['whatsapp'])) {
                $user = Auth::user();
                if ($user && $user->whatsapp !== $validatedData['whatsapp']) {
                    $user->update(['whatsapp' => $validatedData['whatsapp']]);
                }
            }

            $this->linkCheckoutVoucher($order, $voucher);
            $order->refresh();

            // 4. Nominal transaksi mengikuti order. VA/QRIS yang sudah terbit
            //    dengan nominal lama dicabut supaya user checkout ulang.
            $transaction = $this->activeTransaction($order);
            $credentialsReset = false;
            if ($transaction) {
                $amountChanged = (float) $transaction->total_amount !== (float) $totalPrice;
                if ($amountChanged) {
                    $credentialsReset = $transaction->virtual_account_no !== null
                        || isset($transaction->metadata['bri_qris']);

                    // VA/QRIS yang terbit dengan nominal lama tidak lagi bisa
                    // dipakai: webhook BRI membandingkan angka payload dengan
                    // `total_amount` transaksi, jadi user harus checkout ulang.
                    $metadata = $transaction->metadata ?? [];
                    unset($metadata['bri_qris']);

                    $transaction->update([
                        'amount' => $totalPrice,
                        'total_amount' => $totalPrice,
                        'virtual_account_no' => null,
                        'virtual_account_expiry' => null,
                        'payment_url' => null,
                        'metadata' => $metadata,
                    ]);
                }

                if ($request->has('payment_type') && $validatedData['payment_type'] !== null) {
                    $this->tagCheckoutPaymentType($transaction, $validatedData['payment_type']);
                }
            }

            event(new OrderStatusUpdated([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value ?? (string) $order->status,
                'payment_status' => $order->payment_status->value ?? (string) $order->payment_status,
                'updated_at' => now()->toISOString(),
            ], $order->user_id));

            return response()->json([
                'status' => 'success',
                'message' => __('Pesanan berhasil diperbarui'),
                'data' => array_merge($this->orderDetailPayload($order->fresh()), [
                    'credentials_reset' => $credentialsReset,
                    'payment_methods' => $this->paymentMethodsForResponse(),
                ]),
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
            Log::error('[Order] updateOrder error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui pesanan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Hapus order dari history user. Baris order dan transaksinya tetap ada
     * untuk audit, laporan, dan callback BRI yang masih bisa masuk.
     */
    public function deleteOrder($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            if (! $this->canDeleteOrder($order)) {
                return response()->json([
                    'status' => 'error',
                    'message' => $order->payment_status === OrderPaymentStatus::PAID
                        ? __('Pesanan yang sudah dibayar tidak dapat dihapus. Hubungi admin untuk refund.')
                        : __('Pesanan yang sedang diproses tidak dapat dihapus'),
                ], 409);
            }

            $order->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Pesanan berhasil dihapus dari riwayat'),
                'data' => [
                    'id' => (int) $order->id,
                    'order_number' => $order->order_number,
                    'deleted_at' => $order->deleted_at?->toIso8601String(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Pesanan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            Log::error('[Order] deleteOrder error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus pesanan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Payload detail order: kolom model + field history checkout.
     */
    private function orderDetailPayload(Order $order): array
    {
        $history = $this->checkoutHistoryFields($order);

        return array_merge($order->toArray(), $history);
    }

    /**
     * Field riwayat pembayaran yang dibutuhkan daftar dan detail order mobile:
     * metode yang dipakai, nomor VA / payload QRIS, nominal, deadline, dan
     * status ulasan (mengikuti flow marketplace: nilai setelah pesanan selesai).
     */
    private function checkoutHistoryFields(Order $order, ?array $reviewInfo = null): array
    {
        $transaction = $order->latestTransaction;
        $type = $transaction?->checkout_payment_type;
        $deadline = PaymentWindow::deadline($order, $transaction);
        $amount = PaymentWindow::amount($order, $transaction);
        $status = $order->payment_status instanceof OrderPaymentStatus
            ? $order->payment_status->value
            : (string) ($order->payment_status ?? 'unpaid');

        if ($type === null && $transaction) {
            $type = match ($transaction->payment_gateway) {
                'bri_va' => 'virtual_account',
                'bri_qris' => 'qris',
                'midtrans' => 'payment_gateway',
                default => null,
            };
        }

        $reviewInfo ??= $this->reviewInfoForOrder($order, (int) Auth::id());

        return [
            'payment_type' => $type,
            'payment_type_label' => match ($type) {
                'virtual_account' => __('Virtual Account BRI'),
                'qris' => __('QRIS'),
                'payment_gateway' => __('Gerbang Pembayaran'),
                'manual_transfer' => __('Transfer Manual'),
                default => null,
            },
            'transaction_id' => $transaction?->id,
            'transaction_status' => $transaction?->status instanceof PaymentStatus
                ? $transaction->status->value
                : $transaction?->status,
            'event_time' => $this->eventTimeFor($order),
            'va_number' => $type === 'virtual_account' ? $transaction?->virtual_account_no : null,
            'va_name' => $type === 'virtual_account' ? $transaction?->virtual_account_name : null,
            'va_expiry' => $type === 'virtual_account'
                ? $transaction?->virtual_account_expiry?->toIso8601String()
                : null,
            'qris_payload' => $type === 'qris'
                ? data_get($transaction?->metadata, 'bri_qris.qr_content')
                : null,
            'qris_expiry' => $type === 'qris'
                ? (data_get($transaction?->metadata, 'bri_qris.payload.additionalInfo.validityPeriod')
                    ?? data_get($transaction?->metadata, 'bri_qris.validity_period'))
                : null,
            'payment' => [
                'amount' => $amount,
                'amount_formatted' => PaymentWindow::format($amount),
                'deadline' => $deadline?->toIso8601String(),
                'expiry_hours' => PaymentWindow::expiryHours(),
                'is_expired' => PaymentWindow::isExpired($deadline),
                'status' => $status,
                'paid_at' => $transaction?->paid_at?->toIso8601String(),
            ],
            'can_edit' => $this->canEditOrder($order),
            'can_delete' => $this->canDeleteOrder($order),
            'can_pay' => ! in_array($status, ['paid', 'refunded'], true)
                && ! in_array($order->status, [OrderStatus::COMPLETED, OrderStatus::CANCELLED], true),
            'can_confirm_received' => $this->canConfirmReceived($order, $status),
            'review' => $reviewInfo,
            'has_review' => (bool) ($reviewInfo['is_reviewed'] ?? false),
            'can_review' => (bool) ($reviewInfo['can_review'] ?? false),
            'review_id' => $reviewInfo['review_id'] ?? null,
            'shipping' => $this->shippingPayload($order),
        ];
    }

    /**
     * Index ulasan milik user untuk sekumpulan order (menghindari N+1).
     *
     * Review di sistem ini unik per package_id/catalog_id per user, bukan per
     * order, sehingga satu ulasan menandai item tersebut sudah dinilai.
     *
     * @param  array<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function reviewIndexForOrders(array $orders, int $userId): array
    {
        $catalogIds = [];
        $packageIds = [];
        foreach ($orders as $order) {
            if ($order->catalog_id) {
                $catalogIds[] = (int) $order->catalog_id;
            }
            if ($order->package_id) {
                $packageIds[] = (int) $order->package_id;
            }
        }
        $catalogIds = array_values(array_unique($catalogIds));
        $packageIds = array_values(array_unique($packageIds));

        $reviews = collect();
        if ($catalogIds !== [] || $packageIds !== []) {
            $reviews = Review::query()
                ->where('user_id', $userId)
                ->where(function ($q) use ($catalogIds, $packageIds): void {
                    if ($catalogIds !== []) {
                        $q->whereIn('catalog_id', $catalogIds);
                    }
                    if ($packageIds !== []) {
                        $q->orWhereIn('package_id', $packageIds);
                    }
                })
                ->get(['id', 'catalog_id', 'package_id', 'order_id']);
        }

        $byCatalog = [];
        $byPackage = [];
        foreach ($reviews as $review) {
            if ($review->catalog_id) {
                $byCatalog[(int) $review->catalog_id] = $review;
            }
            if ($review->package_id) {
                $byPackage[(int) $review->package_id] = $review;
            }
        }

        $index = [];
        foreach ($orders as $order) {
            $review = null;
            if ($order->package_id && isset($byPackage[(int) $order->package_id])) {
                $review = $byPackage[(int) $order->package_id];
            } elseif ($order->catalog_id && isset($byCatalog[(int) $order->catalog_id])) {
                $review = $byCatalog[(int) $order->catalog_id];
            }
            $index[$order->id] = $this->reviewInfoFromReview($order, $review);
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewInfoForOrder(Order $order, int $userId): array
    {
        if ($userId <= 0) {
            return $this->reviewInfoFromReview($order, null);
        }

        $review = Review::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($order): void {
                $applied = false;
                if ($order->package_id) {
                    $q->where('package_id', $order->package_id);
                    $applied = true;
                }
                if ($order->catalog_id) {
                    $applied
                        ? $q->orWhere('catalog_id', $order->catalog_id)
                        : $q->where('catalog_id', $order->catalog_id);
                }
                if (! $applied) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->first(['id', 'catalog_id', 'package_id', 'order_id']);

        return $this->reviewInfoFromReview($order, $review);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewInfoFromReview(Order $order, ?Review $review): array
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) ($order->status ?? '');
        $isCompleted = $status === OrderStatus::COMPLETED->value || $status === 'completed';
        $isReviewed = $review !== null;
        $item = $order->package ?? $order->catalog;
        $itemName = $item?->name ?? __('Pesanan');
        $itemImage = null;
        if ($order->package) {
            $itemImage = $order->package->image_url ?? $order->package->thumbnail_url ?? null;
        } elseif ($order->catalog) {
            $itemImage = $order->catalog->image_url ?? $order->catalog->thumbnail_url ?? null;
        }

        return [
            'can_review' => $isCompleted && ! $isReviewed && ($order->catalog_id || $order->package_id),
            'is_reviewed' => $isReviewed,
            'review_id' => $review?->id,
            'order_id' => (int) $order->id,
            'catalog_id' => $order->catalog_id ? (int) $order->catalog_id : null,
            'package_id' => $order->package_id ? (int) $order->package_id : null,
            'tier_id' => $order->package_id ? (int) $order->package_id : null,
            'item_name' => $itemName,
            'item_image' => $itemImage,
        ];
    }

    /**
     * Tandai metode checkout aktif pada transaksi. `payment_method` dipakai
     * label display dari gateway, jadi tidak bisa dijadikan sumber kebenaran.
     */
    private function tagCheckoutPaymentType(Transaction $transaction, string $type): void
    {
        if ($transaction->checkout_payment_type === $type) {
            return;
        }

        $transaction->update(['checkout_payment_type' => $type]);
    }

    private function canEditOrder(Order $order): bool
    {
        $paid = in_array($order->payment_status, [
            OrderPaymentStatus::PAID,
            OrderPaymentStatus::REFUNDED,
        ], true);

        return ! $paid && $order->status === OrderStatus::PENDING;
    }

    private function canDeleteOrder(Order $order): bool
    {
        if (in_array($order->payment_status, [OrderPaymentStatus::PAID, OrderPaymentStatus::REFUNDED], true)) {
            return false;
        }

        return ! in_array($order->status, [
            OrderStatus::PREPARING,
            OrderStatus::EVENT_DAY,
            OrderStatus::COMPLETED,
        ], true);
    }

    private function activeTransaction(Order $order): ?Transaction
    {
        return Transaction::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();
    }

    /**
     * Waktu event. Kolom booking_time adalah sumber baru; order lama masih
     * menyimpannya sebagai prefix "Waktu: …" di notes.
     */
    private function eventTimeFor(Order $order): ?string
    {
        return $order->booking_time ?: $this->extractEventTimeNote($order->notes);
    }

    /**
     * Kolom booking_time bertipe TIME, jadi hanya jam yang bisa diterima.
     */
    private function normalizeBookingTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
        }

        return null;
    }

    private function extractEventTimeNote(?string $notes): ?string
    {
        $notes = (string) $notes;
        if (! str_starts_with($notes, 'Waktu: ')) {
            return null;
        }

        $separator = strpos($notes, ' | ');

        return $separator === false
            ? substr($notes, strlen('Waktu: '))
            : substr($notes, strlen('Waktu: '), $separator - strlen('Waktu: '));
    }

    private function stripEventTimeNote(?string $notes): ?string
    {
        $notes = (string) $notes;
        if (! str_starts_with($notes, 'Waktu: ')) {
            return $notes === '' ? null : $notes;
        }

        $separator = strpos($notes, ' | ');
        $rest = $separator === false ? '' : substr($notes, $separator + 3);

        return $rest === '' ? null : $rest;
    }

    private function linkedVoucherCode(Order $order): ?string
    {
        $voucherId = DB::table('user_vouchers')
            ->where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->value('voucher_id');

        return $voucherId ? Voucher::where('id', $voucherId)->value('code') : null;
    }

    private function resolveCheckoutVoucher(?string $code, float $baseTotal): ?Voucher
    {
        if (! filled($code)) {
            return null;
        }

        $voucher = Voucher::with('discount')
            ->where('code', $code)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first(['*']);

        return ($voucher && $voucher->isValidFor($baseTotal)) ? $voucher : null;
    }

    private function linkCheckoutVoucher(Order $order, ?Voucher $voucher): void
    {
        $previousId = DB::table('user_vouchers')
            ->where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->value('voucher_id');

        if ($previousId && (! $voucher || (int) $previousId !== (int) $voucher->id)) {
            DB::table('user_vouchers')
                ->where('order_id', $order->id)
                ->where('user_id', $order->user_id)
                ->update(['order_id' => null]);
        }

        if ($voucher) {
            $voucher->assignToUser($order->user_id);
            $voucher->users()->updateExistingPivot($order->user_id, [
                'order_id' => $order->id,
            ]);
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

                $method = $m->toArray();
                // Rekening settlement merchant bukan nomor VA pelanggan.
                // Jangan kirim data itu sebagai bagian dari metode VA; nomor
                // yang sah hanya dibuat provider setelah order dibuat.
                if ($m->type === 'virtual_account') {
                    $method['account_number'] = null;
                    $method['account_holder'] = null;
                }

                return array_merge($method, [
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
                'error' => null,
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
     * Konfirmasi pesanan diterima/selesai oleh pembeli (flow marketplace).
     */
    public function confirmReceived($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['package.media', 'catalog.media', 'latestTransaction', 'timelines'])
                ->firstOrFail(['*']);

            if ($order->status === OrderStatus::CANCELLED) {
                return response()->json(['status' => 'error', 'message' => __('Pesanan sudah dibatalkan')], 409);
            }
            if ($order->status === OrderStatus::COMPLETED) {
                return response()->json([
                    'status' => 'success',
                    'message' => __('Pesanan sudah selesai'),
                    'data' => $this->orderDetailPayload($order),
                ]);
            }

            $paymentStatus = $order->payment_status instanceof OrderPaymentStatus
                ? $order->payment_status->value
                : (string) $order->payment_status;
            if (! in_array($paymentStatus, ['paid', 'partial'], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan hanya dapat diselesaikan setelah pembayaran terkonfirmasi'),
                ], 409);
            }

            $shippingStatus = (string) ($order->shipping_status ?? '');
            $allowedShipping = ['', 'pending', 'packed', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
            if ($shippingStatus !== '' && ! in_array($shippingStatus, $allowedShipping, true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pesanan tidak dapat dikonfirmasi diterima pada status pengiriman saat ini'),
                ], 409);
            }

            $updates = ['status' => OrderStatus::COMPLETED];
            if ($shippingStatus === 'delivered' && ! $order->delivered_at) {
                $updates['delivered_at'] = now();
            }
            $order->update($updates);

            try {
                \App\Models\Order\OrderTimeline::create([
                    'order_id' => $order->id,
                    'status' => 'completed',
                    'description' => __('Pesanan dikonfirmasi diterima oleh pembeli.'),
                    'metadata' => ['source' => 'buyer_confirm_received'],
                    'created_by' => Auth::id(),
                ]);
            } catch (\Throwable) {
            }

            $order->refresh()->loadMissing(['package.media', 'catalog.media', 'latestTransaction', 'timelines']);

            return response()->json([
                'status' => 'success',
                'message' => __('Pesanan berhasil dikonfirmasi diterima'),
                'data' => $this->orderDetailPayload($order),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan')], 404);
        } catch (\Throwable $e) {
            Log::error('[Order] confirmReceived error: '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => __('Gagal mengonfirmasi pesanan diterima')], 500);
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
                ->with(['user', 'package.category', 'package.media', 'catalog.category', 'catalog.media', 'latestTransaction'])
                ->firstOrFail();

            $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();
            $dompdf = \App\Support\PdfLayout\PdfLayout::apply(new Dompdf);
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
            return response()->json(['status' => 'error', 'message' => __('Gagal mengunduh invoice'), 'error' => null], 500);
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
                ->with(['user', 'package.category', 'package.media', 'catalog.category', 'catalog.media', 'latestTransaction'])
                ->firstOrFail();

            $email = $request->input('email', $order->user->email);
            $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();
            $dompdf = \App\Support\PdfLayout\PdfLayout::apply(new Dompdf);
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
            return response()->json(['status' => 'error', 'message' => __('Gagal mengirim invoice'), 'error' => null], 500);
        }
    }

    /**
     * Sinkronisasi pelacakan resi (J&T, SiCepat, Shopee Express, dll.) dari
     * provider tracking yang dikonfigurasi, lalu kembalikan detail order.
     */
    public function syncTracking(Request $request, $id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['package.media', 'catalog.media', 'latestTransaction', 'timelines'])
                ->firstOrFail(['*']);

            $result = app(OrderShippingSyncService::class)->sync($order, (bool) $request->boolean('force'));
            $order->refresh()->loadMissing(['package.media', 'catalog.media', 'latestTransaction', 'timelines']);

            return response()->json([
                'status' => $result['synced'] ? 'success' : 'error',
                'message' => $result['message'],
                'tracking' => [
                    'synced' => $result['synced'],
                    'shipping_status' => $result['status'],
                    'events_count' => $result['events_count'],
                ],
                'data' => $this->orderDetailPayload($order),
            ], $result['synced'] ? 200 : 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan atau bukan milik Anda')], 404);
        } catch (\Throwable $e) {
            Log::error('[Order] syncTracking error: '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => __('Gagal sinkronisasi pelacakan')], 500);
        }
    }

    private function canConfirmReceived(Order $order, string $paymentStatus): bool
    {
        $status = $order->status instanceof OrderStatus ? $order->status : null;
        if (in_array($status, [OrderStatus::COMPLETED, OrderStatus::CANCELLED], true)) {
            return false;
        }
        if (! in_array($paymentStatus, ['paid', 'partial'], true)) {
            return false;
        }
        $shipping = (string) ($order->shipping_status ?? '');
        if ($shipping !== '' && in_array($shipping, ['returned', 'failed'], true)) {
            return false;
        }
        return true;
    }

    /**
     * Informasi pengiriman & pelacakan (resi) untuk ditampilkan ke user.
     *
     * @return array<string, mixed>
     */
    private function shippingPayload(Order $order): array
    {
        $courier = $order->getAttributeValue('shipping_courier');
        $courierName = $order->getAttributeValue('shipping_courier_name');
        $trackingNumber = $order->getAttributeValue('tracking_number');
        $trackingUrl = $order->getAttributeValue('tracking_url');
        $shippingStatus = $order->getAttributeValue('shipping_status');

        if (! $trackingUrl && filled($trackingNumber)) {
            $trackingUrl = ShippingTracking::trackingUrl($courier, $trackingNumber);
        }

        $hasInfo = filled($courier) || filled($trackingNumber) || filled($trackingUrl) || filled($shippingStatus);

        $events = [];
        try {
            $relation = $order->relationLoaded('timelines')
                ? $order->getRelation('timelines')
                : $order->timelines()->where('status', 'shipping')->latest('id')->limit(30)->get();
            $rows = collect($relation)
                ->filter(fn ($row) => ($row->status ?? '') === 'shipping')
                ->sortByDesc(fn ($row) => (is_array($row->metadata ?? null) ? ($row->metadata['time'] ?? '') : '') . '|' . (string) $row->id)
                ->values();
            foreach ($rows as $row) {
                $meta = is_array($row->metadata ?? null) ? $row->metadata : [];
                $events[] = [
                    'time' => $meta['time'] ?? optional($row->created_at)->toIso8601String(),
                    'description' => $row->description,
                    'location' => $meta['location'] ?? null,
                    'status' => $meta['checkpoint_status'] ?? null,
                ];
            }
        } catch (\Throwable) {
            $events = [];
        }

        return [
            'has_info' => (bool) $hasInfo,
            'courier' => $courier,
            'courier_name' => ShippingTracking::courierName($courier, $courierName),
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'status' => $shippingStatus,
            'status_label' => $shippingStatus ? (ShippingTracking::shippingStatuses()[$shippingStatus] ?? $shippingStatus) : null,
            'shipped_at' => optional($order->getAttributeValue('shipped_at'))->toIso8601String(),
            'delivered_at' => optional($order->getAttributeValue('delivered_at'))->toIso8601String(),
            'notes' => $order->getAttributeValue('shipping_notes'),
            'events' => $events,
        ];
    }
}
