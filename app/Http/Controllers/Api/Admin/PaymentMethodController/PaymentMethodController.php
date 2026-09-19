<?php

namespace App\Http\Controllers\Api\Admin\PaymentMethodController;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentMethod::query();
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('name', 'like', "%{$s}%")->orWhere('account_number', 'like', "%{$s}%");
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            $methods = $query->orderBy('name')->paginate($request->get('per_page', 20));

            return response()->json(['status' => 'success', 'data' => $methods->items(), 'pagination' => [
                'current_page' => $methods->currentPage(), 'last_page' => $methods->lastPage(),
                'per_page' => $methods->perPage(), 'total' => $methods->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil metode pembayaran'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => PaymentMethod::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Metode pembayaran tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:50',
                'code' => 'nullable|string|max:50',
                'deeplink' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:100',
                'account_holder' => 'nullable|string|max:255',
                'image_url' => 'nullable|string|max:255',
                'instructions' => 'nullable|string',
                'fee' => 'numeric|min:0',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);
            $method = PaymentMethod::create($validated);

            return response()->json(['status' => 'success', 'message' => __('Metode pembayaran berhasil dibuat'), 'data' => ['id' => $method->id]], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat metode pembayaran'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $method = PaymentMethod::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'type' => 'nullable|string|max:50',
                'code' => 'nullable|string|max:50',
                'deeplink' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:100',
                'account_holder' => 'nullable|string|max:255',
                'image_url' => 'nullable|string|max:255',
                'instructions' => 'nullable|string',
                'fee' => 'numeric|min:0',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);
            $method->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Metode pembayaran berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui metode pembayaran'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            PaymentMethod::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Metode pembayaran berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus metode pembayaran')], 500);
        }
    }
}
