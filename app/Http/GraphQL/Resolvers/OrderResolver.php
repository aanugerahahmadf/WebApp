<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Order\Order;
use App\Models\Order\OrderPayment;
use App\Models\User\UserAddress;
use App\Models\Voucher\Voucher;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class OrderResolver
{
    /**
     * Get current user's orders
     */
    public function myOrders($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            return [];
        }
        
        $query = Order::where('user_id', $user->id)
            ->with(['catalog', 'package', 'catalog.category', 'package.category', 'voucher']);
        
        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }
        
        return $query->latest()->paginate($args['first'] ?? 10);
    }

    /**
     * Get order by order number
     */
    public function byNumber($root, array $args, GraphQLContext $context)
    {
        $order = Order::where('order_number', $args['orderNumber'])
            ->with(['catalog', 'package', 'catalog.category', 'package.category', 'voucher', 'payment', 'user'])
            ->firstOrFail();
        
        // Check authorization
        $user = $context->user();
        if ($user && !$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        return $order;
    }

    /**
     * Admin: Get all orders
     */
    public function all($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $query = Order::with(['user', 'catalog', 'package', 'voucher', 'payment']);
        
        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }
        
        if (isset($args['paymentStatus'])) {
            $query->where('payment_status', $args['paymentStatus']);
        }
        
        if (isset($args['userId'])) {
            $query->where('user_id', $args['userId']);
        }
        
        if (isset($args['dateFrom'])) {
            $query->whereDate('created_at', '>=', $args['dateFrom']);
        }
        
        if (isset($args['dateTo'])) {
            $query->whereDate('created_at', '<=', $args['dateTo']);
        }
        
        return $query->latest()->paginate($args['first'] ?? 20);
    }

    /**
     * Get order statistics (admin)
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $confirmedOrders = Order::where('status', 'confirmed')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $shippedOrders = Order::where('status', 'shipped')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_price');
        $pendingRevenue = Order::where('payment_status', 'pending')->sum('total_price');
        $thisMonthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price');
        
        $averageOrderValue = Order::where('payment_status', 'paid')->avg('total_price') ?? 0;
        
        $byStatus = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn($item) => ['status' => $item->status, 'count' => $item->count]);
        
        $byPaymentStatus = Order::selectRaw('payment_status, count(*) as count')
            ->groupBy('payment_status')
            ->get()
            ->map(fn($item) => ['paymentStatus' => $item->payment_status, 'count' => $item->count]);
        
        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'confirmedOrders' => $confirmedOrders,
            'processingOrders' => $processingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'totalRevenue' => $totalRevenue,
            'pendingRevenue' => $pendingRevenue,
            'thisMonthRevenue' => $thisMonthRevenue,
            'averageOrderValue' => $averageOrderValue,
            'byStatus' => $byStatus,
            'byPaymentStatus' => $byPaymentStatus,
        ];
    }

    /**
     * Get order timeline
     */
    public function timeline($root, array $args, GraphQLContext $context)
    {
        $order = Order::findOrFail($args['orderId']);
        
        $user = $context->user();
        if ($user && !$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        // Get timeline from order status history or create from order events
        return \App\Models\Order\OrderTimeline::where('order_id', $order->id)
            ->with('createdBy')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get order address
     */
    public function address(Order $order)
    {
        if ($order->address_id) {
            return UserAddress::find($order->address_id);
        }
        
        // Fallback to user's default address
        return UserAddress::where('user_id', $order->user_id)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get order voucher
     */
    public function voucher(Order $order)
    {
        if ($order->voucher_id) {
            return Voucher::find($order->voucher_id);
        }
        return null;
    }

    /**
     * Get order payment
     */
    public function payment(Order $order)
    {
        return OrderPayment::where('order_id', $order->id)->first();
    }

    /**
     * Create new order
     */
    public function store($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to create order');
        }
        
        $input = $args['input'];
        
        // Validate catalog or package
        if (!$input['catalogId'] && !$input['packageId']) {
            throw new \Exception('Catalog or Package is required');
        }
        
        if ($input['catalogId'] && $input['packageId']) {
            throw new \Exception('Cannot order both catalog and package at once');
        }
        
        // Calculate price
        $price = 0;
        $discountPrice = 0;
        
        if ($input['catalogId']) {
            $catalog = \App\Models\Catalog\Catalog::findOrFail($input['catalogId']);
            $price = $catalog->price;
            $discountPrice = $catalog->discount_price ?? 0;
        } else {
            $package = \App\Models\Package\Package::findOrFail($input['packageId']);
            $price = $package->price;
            $discountPrice = $package->discount_price ?? 0;
        }
        
        $quantity = $input['quantity'] ?? 1;
        $subtotal = ($discountPrice ?: $price) * $quantity;
        $discount = $discountPrice ? ($price - $discountPrice) * $quantity : 0;
        
        // Apply voucher if provided
        $voucherDiscount = 0;
        $voucher = null;
        
        if ($input['voucherCode']) {
            $voucher = Voucher::where('code', $input['voucherCode'])
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
        
        // Create order
        $order = Order::create([
            'user_id' => $user->id,
            'catalog_id' => $input['catalogId'],
            'package_id' => $input['packageId'],
            'quantity' => $quantity,
            'booking_date' => $input['bookingDate'] ?? null,
            'notes' => $input['notes'] ?? null,
            'address_id' => $input['addressId'],
            'voucher_id' => $voucher?->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        
        // Increment voucher usage
        if ($voucher) {
            $voucher->increment('uses_count');
        }
        
        // Create timeline entry
        \App\Models\Order\OrderTimeline::create([
            'order_id' => $order->id,
            'status' => 'pending',
            'description' => 'Order created',
            'created_by' => $user->id,
        ]);
        
        return $order->load(['catalog', 'package', 'voucher', 'address']);
    }

    /**
     * Cancel order
     */
    public function cancel($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $order = Order::findOrFail($args['orderId']);
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            throw new \Exception('Order cannot be cancelled');
        }
        
        $order->status = 'cancelled';
        $order->save();
        
        // Create timeline entry
        \App\Models\Order\OrderTimeline::create([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'description' => 'Order cancelled: ' . $args['input']['reason'],
            'created_by' => $user->id,
        ]);
        
        return $order->fresh();
    }

    /**
     * Reorder from existing order
     */
    public function reorder($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $order = Order::findOrFail($args['orderId']);
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        // Create new order with same details
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
        
        return $newOrder->load(['catalog', 'package', 'address']);
    }

    /**
     * Admin: Update order
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $order = Order::findOrFail($args['id']);
        $input = $args['input'];
        
        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;
        
        $order->update($input);
        
        // Create timeline entry if status changed
        if (isset($input['status']) && $input['status'] !== $oldStatus) {
            \App\Models\Order\OrderTimeline::create([
                'order_id' => $order->id,
                'status' => $input['status'],
                'description' => 'Status changed from ' . $oldStatus . ' to ' . $input['status'],
                'created_by' => $user->id,
            ]);
        }
        
        if (isset($input['payment_status']) && $input['payment_status'] !== $oldPaymentStatus) {
            \App\Models\Order\OrderTimeline::create([
                'order_id' => $order->id,
                'status' => $input['payment_status'],
                'description' => 'Payment status changed from ' . $oldPaymentStatus . ' to ' . $input['payment_status'],
                'created_by' => $user->id,
            ]);
        }
        
        return $order->fresh();
    }

    /**
     * Admin: Update order status
     */
    public function updateStatus($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $order = Order::findOrFail($args['orderId']);
        $oldStatus = $order->status;
        
        $order->status = $args['status'];
        $order->save();
        
        \App\Models\Order\OrderTimeline::create([
            'order_id' => $order->id,
            'status' => $args['status'],
            'description' => 'Status changed from ' . $oldStatus . ' to ' . $args['status'],
            'created_by' => $user->id,
        ]);
        
        return $order->fresh();
    }

    /**
     * Admin: Update payment status
     */
    public function updatePaymentStatus($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $order = Order::findOrFail($args['orderId']);
        $oldStatus = $order->payment_status;
        
        $order->payment_status = $args['paymentStatus'];
        $order->save();
        
        \App\Models\Order\OrderTimeline::create([
            'order_id' => $order->id,
            'status' => $args['paymentStatus'],
            'description' => 'Payment status changed from ' . $oldStatus . ' to ' . $args['paymentStatus'],
            'created_by' => $user->id,
        ]);
        
        return $order->fresh();
    }

    /**
     * Process payment callback
     */
    public function processPayment($root, array $args, GraphQLContext $context)
    {
        $order = Order::findOrFail($args['orderId']);
        $paymentData = $args['paymentData'];
        
        // Update payment record
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
        
        // Update order status
        if (($paymentData['status'] ?? '') === 'paid') {
            $order->payment_status = 'paid';
            $order->status = 'confirmed';
            $order->save();
            
            \App\Models\Order\OrderTimeline::create([
                'order_id' => $order->id,
                'status' => 'confirmed',
                'description' => 'Payment confirmed',
                'created_by' => $order->user_id,
            ]);
        }
        
        return [
            'success' => true,
            'order' => $order->fresh(),
            'redirectUrl' => null,
            'message' => 'Payment processed successfully',
        ];
    }

    /**
     * Generate invoice
     */
    public function generateInvoice($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $order = Order::findOrFail($args['orderId']);
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        // Generate invoice logic here
        // For now, return mock data
        return [
            'id' => 'inv_' . $order->id,
            'order' => $order,
            'invoiceNumber' => 'INV-' . date('Ymd') . '-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
            'pdfUrl' => route('orders.invoice', $order->id),
            'issuedAt' => now(),
            'dueAt' => now()->addDays(7),
            'status' => 'issued',
        ];
    }

    /**
     * Request refund
     */
    public function requestRefund($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $order = Order::findOrFail($args['orderId']);
        
        if (!$user->hasRole('admin') && $order->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        if ($order->payment_status !== 'paid') {
            throw new \Exception('Only paid orders can be refunded');
        }
        
        $refund = \App\Models\Order\RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'reason' => $args['reason'],
            'amount' => $args['amount'] ?? $order->total_price,
            'evidence_photos' => $args['evidencePhotos'] ?? [],
            'status' => 'pending',
        ]);
        
        return $refund;
    }
}