<?php

namespace App\Http\Controllers\Api\Admin\TransactionController;

use App\Http\Controllers\Controller;
use App\Models\Transaction\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Transaction::with('user:id,full_name,email');

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('reference_number', 'like', "%{$s}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('full_name', 'like', "%{$s}%"));
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $transactions = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

            $data = collect($transactions->items())->map(fn ($t) => [
                'id' => $t->id,
                'user_id' => $t->user_id,
                'user_name' => $t->user?->full_name,
                'order_id' => $t->order_id,
                'type' => $t->type->value,
                'reference_number' => $t->reference_number,
                'amount' => $t->amount,
                'admin_fee' => $t->admin_fee,
                'total_amount' => $t->total_amount,
                'payment_gateway' => $t->payment_gateway,
                'payment_method' => $t->payment_method,
                'status' => $t->status->value,
                'paid_at' => $t->paid_at?->toISOString(),
                'notes' => $t->notes,
                'created_at' => $t->created_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil transaksi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $transaction = Transaction::with(['user:id,full_name,email', 'order'])->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $transaction]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Transaksi tidak ditemukan')], 404);
        }
    }
}
