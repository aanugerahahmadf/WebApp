<?php

namespace App\Http\Controllers\Api\User\VoucherController;

use App\Http\Controllers\Controller;
use App\Models\Voucher\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $user = $request->user();
        $query = Voucher::with('discount')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($request->filled('code')) {
            $query->where('code', $request->code);
        }

        if ($request->filled('min_purchase')) {
            $query->whereHas('discount', function ($q) use ($request): void {
                $q->where('min_purchase', '<=', $request->min_purchase);
            });
        }

        $vouchers = $query->get();

        $claimedVoucherIds = $user
            ? $user->vouchers()->pluck('vouchers.id')->toArray()
            : [];

        $vouchers->transform(function ($voucher) use ($locale, $claimedVoucherIds) {
            $voucher->description = $voucher->trans('description', $locale);
            $isClaimed = in_array($voucher->id, $claimedVoucherIds);
            $voucher->setAttribute('is_claimed', $isClaimed);
            if (! $isClaimed) {
                $voucher->setAttribute('code', null);
            }

            return $voucher;
        });

        return response()->json([
            'status' => 'success',
            'data' => $vouchers,
        ]);
    }

    public function claim(Request $request, Voucher $voucher): JsonResponse
    {
        $locale = app()->getLocale();
        $user = $request->user();

        if (! $voucher->is_active || ($voucher->expires_at && $voucher->expires_at->isPast())) {
            return response()->json([
                'status' => 'error',
                'message' => __('Voucher tidak valid atau sudah kadaluarsa'),
            ], 400);
        }

        if ($voucher->max_uses && $voucher->uses_count >= $voucher->max_uses) {
            return response()->json([
                'status' => 'error',
                'message' => __('Voucher sudah habis digunakan'),
            ], 400);
        }

        $voucher->assignToUser($user->id);

        $voucher->description = $voucher->trans('description', $locale);

        return response()->json([
            'status' => 'success',
            'message' => __('Voucher berhasil diklaim'),
            'data' => $voucher,
        ]);
    }

    public function validateVoucher(Request $request)
    {
        $locale = app()->getLocale();

        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $voucher = Voucher::with('discount')
            ->where('code', $request->code)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->first(['*']);

        if (! $voucher || ! $voucher->isValidFor((float) $request->amount)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Voucher tidak valid atau sudah kadaluarsa'),
            ], 404);
        }

        $voucher->description = $voucher->trans('description', $locale);

        return response()->json([
            'status' => 'success',
            'data' => $voucher,
        ]);
    }
}
