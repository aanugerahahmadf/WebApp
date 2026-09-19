<?php

namespace App\Http\Controllers\Api\User\WalletController;

use App\Http\Controllers\Controller;
use App\Models\History\History;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function getWalletData(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => $request->user()->balance,
            ],
        ]);
    }

    public function getHistory(Request $request)
    {
        $histories = History::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $histories->items(),
            'pagination' => [
                'current_page' => $histories->currentPage(),
                'last_page' => $histories->lastPage(),
                'per_page' => $histories->perPage(),
                'total' => $histories->total(),
            ],
        ]);
    }
}
