<?php

namespace App\Http\Controllers\Api\Admin\HelpController;

use App\Http\Controllers\Controller;
use App\Models\Help\Help;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HelpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Help::query();
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where('title', 'like', "%{$s}%");
            }
            $helps = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));
            $data = collect($helps->items())->map(fn ($h) => [
                'id' => $h->id,
                'title' => $h->title,
                'subtitle' => $h->subtitle,
                'faqs' => $h->faqs,
                'contact_options' => $h->contact_options,
                'title_translations' => $h->getRawOriginal('title_translations'),
                'subtitle_translations' => $h->getRawOriginal('subtitle_translations'),
                'faqs_translations' => $h->getRawOriginal('faqs_translations'),
                'created_at' => $h->created_at?->toISOString(),
                'updated_at' => $h->updated_at?->toISOString(),
            ]);

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $helps->currentPage(), 'last_page' => $helps->lastPage(),
                'per_page' => $helps->perPage(), 'total' => $helps->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil halaman bantuan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string',
                'faqs' => 'nullable|array',
                'faqs.*.question' => 'required_with:faqs|string',
                'faqs.*.answer' => 'required_with:faqs|string',
                'contact_options' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'subtitle_translations' => 'nullable|json',
                'faqs_translations' => 'nullable|json',
            ]);
            $help = Help::create($validated);

            return response()->json(['status' => 'success', 'message' => __('Halaman bantuan berhasil dibuat'), 'data' => ['id' => $help->id]], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat halaman bantuan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $help = Help::findOrFail($id);
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'subtitle' => 'nullable|string',
                'faqs' => 'nullable|array',
                'faqs.*.question' => 'required_with:faqs|string',
                'faqs.*.answer' => 'required_with:faqs|string',
                'contact_options' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'subtitle_translations' => 'nullable|json',
                'faqs_translations' => 'nullable|json',
            ]);
            $help->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Halaman bantuan berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui halaman bantuan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $help = Help::findOrFail($id);

            return response()->json(['status' => 'success', 'data' => [
                'id' => $help->id,
                'title' => $help->title,
                'subtitle' => $help->subtitle,
                'faqs' => $help->faqs,
                'contact_options' => $help->contact_options,
                'title_translations' => $help->getRawOriginal('title_translations'),
                'subtitle_translations' => $help->getRawOriginal('subtitle_translations'),
                'faqs_translations' => $help->getRawOriginal('faqs_translations'),
                'created_at' => $help->created_at?->toISOString(),
                'updated_at' => $help->updated_at?->toISOString(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Halaman bantuan tidak ditemukan')], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Help::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Halaman bantuan berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus halaman bantuan')], 500);
        }
    }
}
