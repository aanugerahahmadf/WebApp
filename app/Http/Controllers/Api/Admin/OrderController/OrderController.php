<?php

namespace App\Http\Controllers\Api\Admin\OrderController;

use App\Events\OrderStatusUpdated\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user:id,full_name,email,whatsapp', 'package:id,name', 'product:id,name']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('order_number', 'like', "%{$s}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('full_name', 'like', "%{$s}%"));
                });
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            $data = collect($orders->items())->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'user_id' => $o->user_id,
                'user_name' => $o->user?->full_name,
                'user_email' => $o->user?->email,
                'package_id' => $o->package_id,
                'product_id' => $o->product_id,
                'package_name' => $o->package?->name,
                'product_name' => $o->product?->name,
                'total_price' => $o->total_price,
                'status' => $o->status->value,
                'payment_status' => $o->payment_status->value,
                'booking_date' => $o->booking_date?->toISOString(),
                'booking_time' => $o->booking_time,
                'quantity' => $o->quantity,
                'notes' => $o->notes,
                'created_at' => $o->created_at?->toISOString(),
                'updated_at' => $o->updated_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil pesanan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = Order::with(['user:id,full_name,email,whatsapp', 'package', 'product', 'transactions'])->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Pesanan tidak ditemukan')], 404);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:pending,confirmed,processing,completed,cancelled',
                'notes' => 'nullable|string',
            ]);

            $order = Order::findOrFail($id);
            $order->update(['status' => $validated['status']]);

            if (! empty($validated['notes'])) {
                $order->update(['notes' => $validated['notes']]);
            }

            OrderStatusUpdated::dispatch([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value ?? $order->status,
                'payment_status' => $order->payment_status->value ?? $order->payment_status,
                'updated_at' => now()->toISOString(),
            ], $order->user_id);

            return response()->json([
                'status' => 'success',
                'message' => __('Status pesanan berhasil diperbarui'),
                'data' => ['status' => $order->status->value],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui status pesanan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
        ]);
    }
}
