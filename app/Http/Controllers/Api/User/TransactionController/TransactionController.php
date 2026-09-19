<?php

namespace App\Http\Controllers\Api\User\TransactionController;

use App\Http\Controllers\Controller;
use App\Models\Transaction\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Transaction::where('user_id', $request->user()->id)
                ->with(['order' => function ($q) {
                    $q->select('id', 'order_number', 'total_price', 'status');
                }]);

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $sortDir);

            $transactions = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'has_more_pages' => $transactions->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil riwayat transaksi'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $transaction = Transaction::where('user_id', $request->user()->id)
                ->with(['order'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Transaksi tidak ditemukan'),
            ], 404);
        }
    }
}
