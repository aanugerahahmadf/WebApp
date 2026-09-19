<?php

namespace App\Http\Controllers\Api\Admin\BankController;

use App\Http\Controllers\Controller;
use App\Models\Bank\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Bank::query();
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
                });
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            $banks = $query->orderBy('name')->paginate($request->get('per_page', 20));
            return response()->json(['status' => 'success', 'data' => $banks->items(), 'pagination' => [
                'current_page' => $banks->currentPage(), 'last_page' => $banks->lastPage(),
                'per_page' => $banks->perPage(), 'total' => $banks->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil bank'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => Bank::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Bank tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50',
                'type' => 'nullable|string|max:50',
                'account_number' => 'nullable|string|max:50',
                'account_holder' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                'qris_payload' => 'nullable|string',
                'qris_image' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);
            $bank = Bank::create($validated);
            return response()->json(['status' => 'success', 'message' => __('Bank berhasil dibuat'), 'data' => ['id' => $bank->id]], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat bank'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $bank = Bank::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'code' => 'nullable|string|max:50',
                'type' => 'nullable|string|max:50',
                'account_number' => 'nullable|string|max:50',
                'account_holder' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                'qris_payload' => 'nullable|string',
                'qris_image' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);
            $bank->update($validated);
            return response()->json(['status' => 'success', 'message' => __('Bank berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui bank'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Bank::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Bank berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus bank')], 500);
        }
    }
}
