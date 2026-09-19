<?php

namespace App\Http\Controllers\Api\Admin\DiscountController;

use App\Enums\DiscountType\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Discount::with('discountable');

            if ($request->filled('search')) {
                $s = $request->search;
                $query->whereHasMorph('discountable', [Package::class, Product::class], function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%");
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            if ($request->filled('discountable_type')) {
                $query->where('discountable_type', $request->discountable_type);
            }
            if ($request->filled('discountable_id')) {
                $query->where('discountable_id', $request->discountable_id);
            }

            $discounts = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $discounts->items(),
                'pagination' => [
                    'current_page' => $discounts->currentPage(),
                    'last_page' => $discounts->lastPage(),
                    'per_page' => $discounts->perPage(),
                    'total' => $discounts->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil diskon'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $discount = Discount::with('discountable')->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $discount]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Diskon tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'description' => 'nullable|string',
                'discountable_type' => 'nullable|string',
                'discountable_id' => 'nullable|integer',
                'type' => 'required|string|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_purchase' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ]);

            // Validate polymorphic relation
            if (! empty($validated['discountable_type']) && ! empty($validated['discountable_id'])) {
                $modelClass = $validated['discountable_type'];
                if (! in_array($modelClass, [Package::class, Product::class])) {
                    return response()->json(['status' => 'error', 'message' => __('Tipe item tidak valid')], 422);
                }
                $modelClass::findOrFail($validated['discountable_id']);
            }

            $discount = Discount::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => __('Diskon berhasil dibuat'),
                'data' => ['id' => $discount->id],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat diskon'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $discount = Discount::findOrFail($id);

            $validated = $request->validate([
                'description' => 'nullable|string',
                'type' => 'sometimes|string|in:percentage,fixed',
                'value' => 'sometimes|numeric|min:0',
                'min_purchase' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ]);

            $discount->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Diskon berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui diskon'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $discount = Discount::findOrFail($id);
            $discount->delete();
            return response()->json(['status' => 'success', 'message' => __('Diskon berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus diskon')], 500);
        }
    }
}
