<?php

namespace App\Http\Controllers\Api\Admin\ReferenceOptionController;

use App\Http\Controllers\Controller;
use App\Models\ReferenceOption\ReferenceOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceOptionController extends Controller
{
    /**
     * Normalisasi nilai `label`: terima array atau string JSON.
     */
    private function normalizeLabel(mixed $value): ?array
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
            $query = ReferenceOption::query();

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('type', 'like', "%{$s}%")
                        ->orWhere('key', 'like', "%{$s}%")
                        ->orWhere('label', 'like', "%{$s}%");
                });
            }

            $items = $query->orderBy('type')
                ->orderBy('sort_order')
                ->paginate($request->get('per_page', 20));

            $data = collect($items->items())->map(fn (ReferenceOption $option) => [
                'id' => $option->id,
                'type' => $option->type,
                'key' => $option->key,
                'label' => $option->label,
                'label_en' => $option->getLabelForLocale('en'),
                'label_id' => $option->getLabelForLocale('id'),
                'sort_order' => $option->sort_order,
                'is_active' => $option->is_active,
                'created_at' => $option->created_at?->toISOString(),
                'updated_at' => $option->updated_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil opsi referensi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $option = ReferenceOption::findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $option]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Opsi referensi tidak ditemukan')], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|max:100',
                'key' => ['required', 'string', 'max:100', Rule::unique('reference_options', 'key')->where(fn ($q) => $q->where('type', $request->type))],
                'label' => 'required',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);

            $label = $this->normalizeLabel($validated['label']);
            if ($label === null || empty($label['en'])) {
                return response()->json(['status' => 'error', 'message' => __('Label minimal berisi bahasa Inggris (en)')], 422);
            }

            $option = ReferenceOption::create([
                'type' => $validated['type'],
                'key' => $validated['key'],
                'label' => $label,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Opsi referensi berhasil dibuat'),
                'data' => ['id' => $option->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat opsi referensi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $option = ReferenceOption::findOrFail($id);

            $validated = $request->validate([
                'type' => 'sometimes|required|string|max:100',
                'key' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('reference_options', 'key')->where(fn ($q) => $q->where('type', $validated['type'] ?? $option->type))->ignore($option->id)],
                'label' => 'sometimes|required',
                'sort_order' => 'nullable|integer',
                'is_active' => 'boolean',
            ]);

            $updateData = $validated;
            if (array_key_exists('label', $updateData)) {
                $label = $this->normalizeLabel($updateData['label']);
                if ($label === null || empty($label['en'])) {
                    return response()->json(['status' => 'error', 'message' => __('Label minimal berisi bahasa Inggris (en)')], 422);
                }
                $updateData['label'] = $label;
            }

            $option->update($updateData);

            return response()->json(['status' => 'success', 'message' => __('Opsi referensi berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui opsi referensi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $option = ReferenceOption::findOrFail($id);
            $option->delete();

            return response()->json(['status' => 'success', 'message' => __('Opsi referensi berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus opsi referensi')], 500);
        }
    }
}
