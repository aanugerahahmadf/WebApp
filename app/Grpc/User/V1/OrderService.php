<?php

namespace App\Grpc\User\V1;

use App\Http\GraphQL\Resolvers\OrderResolver;
use App\Models\Order\Order;
use App\Models\User\User;
use Spiral\RoadRunner\GRPC\ContextInterface;

class OrderService extends BaseGrpcService
{
    public function __construct(private OrderResolver $orderResolver) {}

    public function GetMyOrders(GetMyOrdersRequest $request, ContextInterface $context): OrderListResponse
    {
        try {
            $user = $this->requireAuth($context);
            $query = Order::where('user_id', $user->id)->with(['user', 'catalog', 'package', 'payment', 'timeline']);
            if ($request->status) {
                $query->where('status', $request->status);
            }
            $pagination = $request->pagination;
            $perPage = $pagination && $pagination->per_page > 0 ? $pagination->per_page : 10;
            $page = $pagination && $pagination->page > 0 ? $pagination->page : 1;
            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
            return new OrderListResponse([
                'orders' => array_map([$this, 'transformOrder'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetOrder(GetOrderRequest $request, ContextInterface $context): Order
    {
        try {
            $user = $this->requireAuth($context);
            $order = Order::with(['user', 'catalog', 'package', 'payment', 'timeline'])
                ->where('user_id', $user->id)->findOrFail($request->id);
            return $this->transformOrder($order);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function GetOrderByNumber(GetOrderByNumberRequest $request, ContextInterface $context): Order
    {
        try {
            $user = $this->requireAuth($context);
            $order = Order::with(['user', 'catalog', 'package', 'payment', 'timeline'])
                ->where('order_number', $request->order_number)
                ->where('user_id', $user->id)->firstOrFail();
            return $this->transformOrder($order);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function GetOrders(GetOrdersRequest $request, ContextInterface $context): OrderListResponse
    {
        try {
            $this->requireAuth($context);
            $query = Order::with(['user', 'catalog', 'package', 'payment', 'timeline']);
            if ($request->status) $query->where('status', $request->status);
            if ($request->payment_status) $query->where('payment_status', $request->payment_status);
            if ($request->user_id) $query->where('user_id', $request->user_id);
            if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
            if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);
            $pagination = $request->pagination;
            $perPage = $pagination && $pagination->per_page > 0 ? $pagination->per_page : 20;
            $page = $pagination && $pagination->page > 0 ? $pagination->page : 1;
            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
            return new OrderListResponse([
                'orders' => array_map([$this, 'transformOrder'], $paginator->items()),
                'pagination' => $this->createPagination($paginator),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetOrderStats(GetOrderStatsRequest $request, ContextInterface $context): OrderStats
    {
        try {
            $this->requireAuth($context);
            $stats = $this->orderResolver->stats(null, [], $context);
            $byStatus = [];
            foreach ($stats['by_status'] ?? [] as $item) {
                $byStatus[] = new OrderStatusCount(['status' => $item['status'], 'count' => $item['count']]);
            }
            $byPaymentStatus = [];
            foreach ($stats['by_payment_status'] ?? [] as $item) {
                $byPaymentStatus[] = new OrderPaymentStatusCount([
                    'payment_status' => $item['payment_status'], 'count' => $item['count'],
                ]);
            }
            return new OrderStats([
                'total_orders' => $stats['total_orders'] ?? 0,
                'pending_orders' => $stats['pending_orders'] ?? 0,
                'confirmed_orders' => $stats['confirmed_orders'] ?? 0,
                'processing_orders' => $stats['processing_orders'] ?? 0,
                'shipped_orders' => $stats['shipped_orders'] ?? 0,
                'delivered_orders' => $stats['delivered_orders'] ?? 0,
                'cancelled_orders' => $stats['cancelled_orders'] ?? 0,
                'total_revenue' => $stats['total_revenue'] ?? 0,
                'pending_revenue' => $stats['pending_revenue'] ?? 0,
                'this_month_revenue' => $stats['this_month_revenue'] ?? 0,
                'average_order_value' => $stats['average_order_value'] ?? 0,
                'by_status' => $byStatus,
                'by_payment_status' => $byPaymentStatus,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function GetOrderTimeline(GetOrderTimelineRequest $request, ContextInterface $context): OrderTimelineResponse
    {
        try {
            $this->requireAuth($context);
            $timeline = $this->orderResolver->timeline(null, ['orderId' => $request->order_id], $context);
            return new OrderTimelineResponse([
                'timeline' => array_map(fn ($item) => new OrderTimeline([
                    'id' => (string) ($item['id'] ?? ''),
                    'order_id' => (string) ($item['order_id'] ?? $request->order_id),
                    'status' => (string) ($item['status'] ?? ''),
                    'description' => (string) ($item['description'] ?? ''),
                    'created_at' => (string) ($item['created_at'] ?? ''),
                ]), $timeline),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function CreateOrder(CreateOrderRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $input = [
                'catalogId' => $request->catalog_id ?: null,
                'packageId' => $request->package_id ?: null,
                'quantity' => $request->quantity ?: 1,
                'bookingDate' => $request->booking_date ?: null,
                'notes' => $request->notes ?: null,
                'addressId' => $request->address_id,
                'voucherCode' => $request->voucher_code ?: null,
                'customOptions' => $request->custom_options?->data ?? null,
            ];
            $order = $this->orderResolver->store(null, ['input' => $input], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function CancelOrder(CancelOrderRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $input = ['reason' => $request->reason, 'reasonDetail' => $request->reason_detail ?: null];
            $order = $this->orderResolver->cancel(null, [
                'orderId' => $request->order_id, 'input' => $input,
            ], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function Reorder(ReorderRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $order = $this->orderResolver->reorder(null, ['orderId' => $request->order_id], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function UpdateOrder(UpdateOrderRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $input = array_filter([
                'status' => $request->status !== \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_UNSPECIFIED
                    ? $this->mapOrderStatusToString($request->status) : null,
                'paymentStatus' => $request->payment_status !== \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_UNSPECIFIED
                    ? $this->mapPaymentStatusToString($request->payment_status) : null,
                'notes' => $request->notes ?: null,
                'trackingNumber' => $request->tracking_number ?: null,
                'estimatedDelivery' => $request->estimated_delivery ?: null,
            ], fn ($v) => $v !== null);
            $order = $this->orderResolver->update(null, [
                'orderId' => $request->order_id, 'input' => $input,
            ], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function UpdateOrderStatus(UpdateOrderStatusRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $order = $this->orderResolver->updateStatus(null, [
                'orderId' => $request->order_id,
                'status' => $this->mapOrderStatusToString($request->status),
            ], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function UpdatePaymentStatus(UpdatePaymentStatusRequest $request, ContextInterface $context): Order
    {
        try {
            $this->requireAuth($context);
            $order = $this->orderResolver->updatePaymentStatus(null, [
                'orderId' => $request->order_id,
                'paymentStatus' => $this->mapPaymentStatusToString($request->payment_status),
            ], $context);
            return $this->transformOrder($order->load(['user', 'catalog', 'package', 'payment', 'timeline']));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function ProcessPayment(ProcessPaymentRequest $request, ContextInterface $context): PaymentResult
    {
        try {
            $this->requireAuth($context);
            $result = $this->orderResolver->processPayment(null, [
                'orderId' => $request->order_id,
                'paymentData' => $request->payment_data?->data ?? [],
            ], $context);
            return new PaymentResult([
                'success' => $result['success'] ?? false,
                'order' => isset($result['order']) ? $this->transformOrder($result['order']) : null,
                'redirect_url' => $result['redirect_url'] ?? '',
                'message' => $result['message'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return new PaymentResult(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function RequestRefund(RequestRefundRequest $request, ContextInterface $context): RefundRequestResponse
    {
        try {
            $this->requireAuth($context);
            $result = $this->orderResolver->requestRefund(null, [
                'orderId' => $request->order_id,
                'reason' => $request->reason,
                'amount' => $request->amount,
            ], $context);
            return new RefundRequestResponse([
                'id' => (string) ($result['id'] ?? ''),
                'order' => isset($result['order']) ? $this->transformOrder($result['order']) : null,
                'reason' => (string) ($result['reason'] ?? $request->reason),
                'amount' => (float) ($result['amount'] ?? $request->amount),
                'status' => \App\Grpc\User\V1\RefundStatus::REFUND_STATUS_PENDING,
                'requested_at' => (string) ($result['requested_at'] ?? now()->toIso8601String()),
                'processed_at' => (string) ($result['processed_at'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    private function transformOrder(Order $order): Order
    {
        $user = $order->user ? $this->transformUser($order->user) : null;
        $timeline = collect($order->relationLoaded('timeline') ? $order->timeline : [])->map(fn ($t) => new OrderTimeline([
            'id' => (string) $t->id,
            'order_id' => (string) $t->order_id,
            'status' => (string) $t->status,
            'description' => (string) ($t->description ?? ''),
            'created_at' => $this->formatTimestamp($t->created_at),
        ]))->values()->toArray();

        $payment = $order->relationLoaded('payment') && $order->payment ? new OrderPayment([
            'id' => (string) $order->payment->id,
            'method' => (string) $order->payment->method,
            'status' => (string) $order->payment->status,
            'amount' => (float) $order->payment->amount,
            'paid_at' => $this->formatTimestamp($order->payment->paid_at),
            'transaction_id' => (string) ($order->payment->transaction_id ?? ''),
            'proof_url' => (string) ($order->payment->proof_url ?? ''),
        ]) : null;

        return new Order([
            'id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'user' => $user,
            'catalog' => $order->catalog ? new \App\Grpc\User\V1\Catalog([
                'id' => (string) $order->catalog->id,
                'name' => $order->catalog->name,
                'slug' => $order->catalog->slug,
                'price' => (float) $order->catalog->price,
            ]) : null,
            'package' => $order->package ? new \App\Grpc\User\V1\Package([
                'id' => (string) $order->package->id,
                'name' => $order->package->name,
                'slug' => $order->package->slug,
                'price' => (float) $order->package->price,
            ]) : null,
            'total_price' => (float) $order->total_price,
            'status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'booking_date' => $this->formatTimestamp($order->booking_date),
            'quantity' => (int) ($order->quantity ?? 1),
            'notes' => (string) ($order->notes ?? ''),
            'payment' => $payment,
            'timeline' => $timeline,
            'created_at' => $this->formatTimestamp($order->created_at),
            'updated_at' => $this->formatTimestamp($order->updated_at),
        ]);
    }

    private function transformUser(User $user): \App\Grpc\User\V1\User
    {
        return new \App\Grpc\User\V1\User([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'avatar' => $user->avatar ?? '',
            'created_at' => $this->formatTimestamp($user->created_at),
        ]);
    }

    private function mapOrderStatusToString(int $status): string
    {
        return match ($status) {
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_PENDING => 'pending',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_CONFIRMED => 'confirmed',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_PROCESSING => 'processing',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_SHIPPED => 'shipped',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_DELIVERED => 'delivered',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_CANCELLED => 'cancelled',
            \App\Grpc\User\V1\OrderStatus::ORDER_STATUS_REFUNDED => 'refunded',
            default => 'pending',
        };
    }

    private function mapPaymentStatusToString(int $status): string
    {
        return match ($status) {
            \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_PENDING => 'pending',
            \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_PAID => 'paid',
            \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_FAILED => 'failed',
            \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_REFUNDED => 'refunded',
            \App\Grpc\User\V1\PaymentStatus::PAYMENT_STATUS_PARTIALLY_REFUNDED => 'partially_refunded',
            default => 'pending',
        };
    }
}