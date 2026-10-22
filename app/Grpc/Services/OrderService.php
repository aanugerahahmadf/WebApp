<?php

namespace App\Grpc\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderTimeline;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Voucher\Voucher;
use App\Models\User\UserAddress;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class OrderService
{
    public function GetOrder(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::with(['catalog', 'package', 'voucher', 'payment', 'timeline'])
            ->find($request->getId());
        
        $response = new \Weddingapp\Order\OrderResponse();
        if ($order) {
            if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
                $response->setMessage('Unauthorized');
                return $call->sendResponse($response);
            }
            $response->setOrder($this->buildOrderProto($order, $user));
            $response->setMessage('Order found');
        } else {
            $response->setMessage('Order not found');
        }
        return $call->sendResponse($response);
    }

    public function GetOrders(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Order\OrdersResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $query = Order::with(['user', 'catalog', 'package', 'voucher']);
        
        if ($request->getStatus()) {
            $query->where('status', $request->getStatus());
        }
        if ($request->getPaymentStatus()) {
            $query->where('payment_status', $request->getPaymentStatus());
        }
        if ($request->getUserId()) {
            $query->where('user_id', $request->getUserId());
        }
        if ($request->getDateFrom()) {
            $query->whereDate('created_at', '>=', $request->getDateFrom());
        }
        if ($request->getDateTo()) {
            $query->whereDate('created_at', '<=', $request->getDateTo());
        }
        
        $perPage = $request->getPerPage() ?: 20;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Order\OrdersResponse();
        foreach ($paginated->items() as $order) {
            $response->addOrders($this->buildOrderProto($order, $user));
        }
        
        $pagination = new \Weddingapp\Order\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetMyOrders(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = Order::with(['catalog', 'package', 'voucher'])
            ->where('user_id', $user->id);
        
        if ($request->getStatus()) {
            $query->where('status', $request->getStatus());
        }
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Order\OrdersResponse();
        foreach ($paginated->items() as $order) {
            $response->addOrders($this->buildOrderProto($order, $user));
        }
        
        $pagination = new \Weddingapp\Order\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function CreateOrder(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$request->getCatalogId() && !$request->getPackageId()) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Catalog or Package is required');
            return $call->sendResponse($response);
        }
        
        if ($request->getCatalogId() && $request->getPackageId()) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Cannot order both catalog and package at once');
            return $call->sendResponse($response);
        }
        
        $price = 0;
        $discountPrice = 0;
        
        if ($request->getCatalogId()) {
            $catalog = Catalog::find($request->getCatalogId());
            if (!$catalog || !$catalog->is_active || $catalog->stock <= 0) {
                $response = new \Weddingapp\Order\OrderResponse();
                $response->setMessage('Catalog is not available');
                return $call->sendResponse($response);
            }
            if ($request->getQuantity() > $catalog->stock) {
                $response = new \Weddingapp\Order\OrderResponse();
                $response->setMessage('Insufficient stock');
                return $call->sendResponse($response);
            }
            $price = $catalog->price;
            $discountPrice = $catalog->discount_price ?? 0;
        } else {
            $package = Package::find($request->getPackageId());
            if (!$package || !$package->is_active) {
                $response = new \Weddingapp\Order\OrderResponse();
                $response->setMessage('Package is not available');
                return $call->sendResponse($response);
            }
            $price = $package->price;
            $discountPrice = $package->discount_price ?? 0;
        }
        
        $quantity = $request->getQuantity() ?: 1;
        $subtotal = ($discountPrice ?: $price) * $quantity;
        $discount = $discountPrice ? ($price - $discountPrice) * $quantity : 0;
        
        $voucherDiscount = 0;
        $voucher = null;
        
        if ($request->getVoucherCode()) {
            $voucher = Voucher::where('code', $request->getVoucherCode())
                ->where('is_active', true)
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->first();
            
            if ($voucher) {
                if ($voucher->discount_type === 'PERCENTAGE') {
                    $voucherDiscount = $subtotal * ($voucher->discount_amount / 100);
                    if ($voucher->max_discount && $voucherDiscount > $voucher->max_discount) {
                        $voucherDiscount = $voucher->max_discount;
                    }
                } elseif ($voucher->discount_type === 'FIXED_AMOUNT') {
                    $voucherDiscount = min($voucher->discount_amount, $subtotal);
                }
            }
        }
        
        $total = $subtotal - $voucherDiscount;
        
        $order = Order::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
            'quantity' => $quantity,
            'booking_date' => $request->getBookingDate() ?: null,
            'notes' => $request->getNotes() ?: null,
            'address_id' => $request->getAddressId(),
            'voucher_id' => $voucher?->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        
        if ($voucher) {
            $voucher->increment('uses_count');
        }
        
        OrderTimeline::create([
            'order_id' => $order->id,
            'status' => 'pending',
            'description' => 'Order created',
            'created_by' => $user->id,
        ]);
        
        $response = new \Weddingapp\Order\OrderResponse();
        $response->setOrder($this->buildOrderProto($order->fresh(), $user));
        $response->setMessage('Order created successfully');
        
        return $call->sendResponse($response);
    }

    public function UpdateOrderStatus(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $order = Order::find($request->getId());
        if (!$order) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Order not found');
            return $call->sendResponse($response);
        }
        
        $oldStatus = $order->status;
        $order->status = $request->getStatus();
        $order->save();
        
        OrderTimeline::create([
            'order_id' => $order->id,
            'status' => $request->getStatus(),
            'description' => "Status changed from {$oldStatus} to {$request->getStatus()}",
            'created_by' => $user->id,
        ]);
        
        $response = new \Weddingapp\Order\OrderResponse();
        $response->setOrder($this->buildOrderProto($order->fresh(), $user));
        $response->setMessage('Order status updated');
        
        return $call->sendResponse($response);
    }

    public function UpdatePaymentStatus(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $order = Order::find($request->getId());
        if (!$order) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Order not found');
            return $call->sendResponse($response);
        }
        
        $oldStatus = $order->payment_status;
        $order->payment_status = $request->getPaymentStatus();
        $order->save();
        
        OrderTimeline::create([
            'order_id' => $order->id,
            'status' => $request->getPaymentStatus(),
            'description' => "Payment status changed from {$oldStatus} to {$request->getPaymentStatus()}",
            'created_by' => $user->id,
        ]);
        
        $response = new \Weddingapp\Order\OrderResponse();
        $response->setOrder($this->buildOrderProto($order->fresh(), $user));
        $response->setMessage('Payment status updated');
        
        return $call->sendResponse($response);
    }

    public function CancelOrder(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::find($request->getId());
        
        if (!$order) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Order not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Order cannot be cancelled');
            return $call->sendResponse($response);
        }
        
        $order->status = 'cancelled';
        $order->save();
        
        OrderTimeline::create([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'description' => 'Order cancelled: ' . $request->getReason(),
            'created_by' => $user->id,
        ]);
        
        $response = new \Weddingapp\Order\OrderResponse();
        $response->setOrder($this->buildOrderProto($order->fresh(), $user));
        $response->setMessage('Order cancelled successfully');
        
        return $call->sendResponse($response);
    }

    public function Reorder(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::find($request->getOrderId());
        
        if (!$order) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Order not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            $response = new \Weddingapp\Order\OrderResponse();
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        $newOrder = Order::create([
            'user_id' => $user->id,
            'catalog_id' => $order->catalog_id,
            'package_id' => $order->package_id,
            'quantity' => $order->quantity,
            'booking_date' => $order->booking_date,
            'notes' => $order->notes,
            'address_id' => $order->address_id,
            'total_price' => $order->total_price,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        
        $response = new \Weddingapp\Order\OrderResponse();
        $response->setOrder($this->buildOrderProto($newOrder->fresh(), $user));
        $response->setMessage('Order reordered successfully');
        
        return $call->sendResponse($response);
    }

    public function GetOrderTimeline(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::find($request->getOrderId());
        
        if (!$order) {
            $response = new \Weddingapp\Order\OrderTimelineResponse();
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            $response = new \Weddingapp\Order\OrderTimelineResponse();
            return $call->sendResponse($response);
        }
        
        $timeline = OrderTimeline::where('order_id', $order->id)
            ->with('createdBy')
            ->orderBy('created_at')
            ->get();
        
        $response = new \Weddingapp\Order\OrderTimelineResponse();
        foreach ($timeline as $entry) {
            $entryProto = new \Weddingapp\Order\OrderTimeline();
            $entryProto->setId($entry->id);
            $entryProto->setOrderId($entry->order_id);
            $entryProto->setStatus($entry->status);
            $entryProto->setDescription($entry->description);
            $entryProto->setCreatedAt($entry->created_at?->toISOString() ?? '');
            
            if ($entry->createdBy) {
                $userProto = new \Weddingapp\Order\User();
                $userProto->setId($entry->createdBy->id);
                $userProto->setName($entry->createdBy->name);
                $userProto->setEmail($entry->createdBy->email);
                $entryProto->setCreatedBy($userProto);
            }
            
            $response->addTimeline($entryProto);
        }
        
        return $call->sendResponse($response);
    }

    public function GetOrderStats(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Order\OrderStatsResponse();
            return $call->sendResponse($response);
        }
        
        $stats = new \Weddingapp\Order\OrderStats();
        $stats->setTotalOrders(Order::count());
        $stats->setPendingOrders(Order::where('status', 'pending')->count());
        $stats->setConfirmedOrders(Order::where('status', 'confirmed')->count());
        $stats->setProcessingOrders(Order::where('status', 'processing')->count());
        $stats->setShippedOrders(Order::where('status', 'shipped')->count());
        $stats->setDeliveredOrders(Order::where('status', 'delivered')->count());
        $stats->setCancelledOrders(Order::where('status', 'cancelled')->count());
        $stats->setTotalRevenue(Order::where('payment_status', 'paid')->sum('total_price'));
        $stats->setPendingRevenue(Order::where('payment_status', 'pending')->sum('total_price'));
        $stats->setThisMonthRevenue(Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price'));
        $stats->setAverageOrderValue(Order::where('payment_status', 'paid')->avg('total_price') ?? 0);
        
        $byStatus = Order::selectRaw('status, count(*) as count')->groupBy('status')->get();
        foreach ($byStatus as $item) {
            $statusCount = new \Weddingapp\Order\StatusCount();
            $statusCount->setStatus($item->status);
            $statusCount->setCount($item->count);
            $stats->addByStatus($statusCount);
        }
        
        $byPaymentStatus = Order::selectRaw('payment_status, count(*) as count')->groupBy('payment_status')->get();
        foreach ($byPaymentStatus as $item) {
            $paymentCount = new \Weddingapp\Order\PaymentStatusCount();
            $paymentCount->setPaymentStatus($item->payment_status);
            $paymentCount->setCount($item->count);
            $stats->addByPaymentStatus($paymentCount);
        }
        
        $response = new \Weddingapp\Order\OrderStatsResponse();
        $response->setStats($stats);
        
        return $call->sendResponse($response);
    }

    public function ProcessPayment(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::find($request->getOrderId());
        
        if (!$order) {
            $response = new \Weddingapp\Order\PaymentResultResponse();
            $response->setSuccess(false);
            $response->setMessage('Order not found');
            return $call->sendResponse($response);
        }
        
        $paymentData = $request->getPaymentData();
        
        $payment = \App\Models\Order\OrderPayment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'method' => $paymentData['method'] ?? 'MIDTRANS',
                'status' => $paymentData['status'] ?? 'paid',
                'amount' => $paymentData['amount'] ?? $order->total_price,
                'paid_at' => now(),
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'proof_url' => $paymentData['proof_url'] ?? null,
            ]
        );
        
        if (($paymentData['status'] ?? '') === 'paid') {
            $order->payment_status = 'paid';
            $order->status = 'confirmed';
            $order->save();
            
            OrderTimeline::create([
                'order_id' => $order->id,
                'status' => 'confirmed',
                'description' => 'Payment confirmed',
                'created_by' => $order->user_id,
            ]);
        }
        
        $response = new \Weddingapp\Order\PaymentResultResponse();
        $response->setSuccess(true);
        $response->setOrder($this->buildOrderProto($order->fresh(), $user));
        $response->setMessage('Payment processed successfully');
        
        return $call->sendResponse($response);
    }

    public function RequestRefund(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        $order = Order::find($request->getOrderId());
        
        if (!$order) {
            $response = new \Weddingapp\Order\RefundResponse();
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            $response = new \Weddingapp\Order\RefundResponse();
            return $call->sendResponse($response);
        }
        
        if ($order->payment_status !== 'paid') {
            $response = new \Weddingapp\Order\RefundResponse();
            return $call->sendResponse($response);
        }
        
        $refund = \App\Models\Order\RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => $request->getReason(),
            'amount' => $request->getAmount() ?: $order->total_price,
            'evidence_photos' => $request->getEvidencePhotos(),
            'status' => 'pending',
        ]);
        
        $refundProto = new \Weddingapp\Order\RefundRequest();
        $refundProto->setId($refund->id);
        $refundProto->setOrderId($refund->order_id);
        $refundProto->setReason($refund->reason);
        $refundProto->setAmount($refund->amount);
        $refundProto->setStatus('pending');
        $refundProto->setRequestedAt($refund->requested_at?->toISOString() ?? '');
        
        $response = new \Weddingapp\Order\RefundResponse();
        $response->setRefund($refundProto);
        
        return $call->sendResponse($response);
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function buildOrderProto(Order $order, $user): \Weddingapp\Order\Order
    {
        $proto = new \Weddingapp\Order\Order();
        $proto->setId($order->id);
        $proto->setOrderNumber($order->order_number);
        $proto->setUserId($order->user_id);
        $proto->setCatalogId($order->catalog_id ?? 0);
        $proto->setPackageId($order->package_id ?? 0);
        $proto->setTotalPrice($order->total_price);
        $proto->setStatus($order->status);
        $proto->setPaymentStatus($order->payment_status);
        $proto->setBookingDate($order->booking_date ?? '');
        $proto->setQuantity($order->quantity ?? 1);
        $proto->setNotes($order->notes ?? '');
        $proto->setCreatedAt($order->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($order->updated_at?->toISOString() ?? '');
        
        if ($order->address_id) {
            $address = UserAddress::find($order->address_id);
            if ($address) {
                $addrProto = new \Weddingapp\Order\Address();
                $addrProto->setId($address->id);
                $addrProto->setName($address->name);
                $addrProto->setPhone($address->phone);
                $addrProto->setAddress($address->address);
                $addrProto->setCity($address->city);
                $addrProto->setProvince($address->province);
                $addrProto->setPostalCode($address->postal_code);
                $addrProto->setIsDefault($address->is_default);
                $proto->setAddress($addrProto);
            }
        }
        
        if ($order->voucher_id) {
            $voucher = Voucher::find($order->voucher_id);
            if ($voucher) {
                $voucherProto = new \Weddingapp\Order\Voucher();
                $voucherProto->setId($voucher->id);
                $voucherProto->setCode($voucher->code);
                $voucherProto->setName($voucher->name ?? '');
                $voucherProto->setDiscountAmount($voucher->discount_amount);
                $voucherProto->setDiscountType($voucher->discount_type);
                $proto->setVoucher($voucherProto);
            }
        }
        
        if ($order->payment) {
            $paymentProto = new \Weddingapp\Order\Payment();
            $paymentProto->setId($order->payment->id);
            $paymentProto->setMethod($order->payment->method);
            $paymentProto->setStatus($order->payment->status);
            $paymentProto->setAmount($order->payment->amount);
            $paymentProto->setPaidAt($order->payment->paid_at?->toISOString() ?? '');
            $paymentProto->setTransactionId($order->payment->transaction_id ?? '');
            $paymentProto->setProofUrl($order->payment->proof_url ?? '');
            $proto->setPayment($paymentProto);
        }
        
        return $proto;
    }
}