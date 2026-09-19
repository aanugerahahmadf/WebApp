<?php

namespace App\Http\Controllers\Api\Admin\PaymentGatewayController;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    /**
     * Normalisasi nilai `config`: terima array atau string JSON.
     */
    private function normalizeConfig(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentGateway::query();

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('code', 'like', "%{$s}%");
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $items = $query->orderBy('name')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $items->items(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil gateway pembayaran'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => PaymentGateway::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gateway pembayaran tidak ditemukan')], 404);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $gateway = PaymentGateway::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('payment_gateways', 'code')->ignore($gateway->id)],
                'description' => 'nullable|string',
                'config' => 'nullable',
                'is_active' => 'boolean',
            ]);

            $updateData = $validated;
            if (array_key_exists('config', $updateData)) {
                $updateData['config'] = $this->normalizeConfig($updateData['config']);
            }

            $gateway->update($updateData);

            return response()->json(['status' => 'success', 'message' => __('Gateway pembayaran berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui gateway pembayaran'), 'error' => $e->getMessage()], 500);
        }
    }
}
