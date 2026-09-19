<?php

namespace App\Http\Controllers\Api\Admin\LegalPageController;

use App\Http\Controllers\Controller;
use App\Models\LegalPage\LegalPage;
use App\Models\TermsOfService\TermsOfService;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LegalPageController extends Controller
{
    // ── Generic LegalPage CRUD ──

    public function indexPages(Request $request): JsonResponse
    {
        try {
            $query = LegalPage::query();
            if ($request->filled('search')) {
                $query->where('title', 'like', "%{$request->search}%");
            }
            $pages = $query->orderBy('slug')->paginate($request->get('per_page', 20));
            return response()->json(['status' => 'success', 'data' => $pages->items(), 'pagination' => [
                'current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(), 'total' => $pages->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil halaman legal'), 'error' => $e->getMessage()], 500);
        }
    }

    public function showPage(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => LegalPage::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Halaman legal tidak ditemukan')], 404);
        }
    }

    public function storePage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'slug' => 'required|string|max:100|unique:legal_pages,slug',
                'title' => 'required|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $validated['content'] = $validated['content'] ?? [];
            $page = LegalPage::create($validated);
            return response()->json(['status' => 'success', 'message' => __('Halaman legal berhasil dibuat'), 'data' => ['id' => $page->id]], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat halaman legal'), 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        try {
            $page = LegalPage::findOrFail($id);
            $validated = $request->validate([
                'slug' => 'sometimes|string|max:100|unique:legal_pages,slug,' . $page->id,
                'title' => 'sometimes|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $page->update($validated);
            return response()->json(['status' => 'success', 'message' => __('Halaman legal berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui halaman legal'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyPage(int $id): JsonResponse
    {
        try {
            LegalPage::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Halaman legal berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus halaman legal')], 500);
        }
    }

    // ── Terms of Service ──

    public function indexTerms(): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => TermsOfService::all()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil terms of service'), 'error' => $e->getMessage()], 500);
        }
    }

    public function showTerm(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => TermsOfService::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Terms of service tidak ditemukan')], 404);
        }
    }

    public function storeTerm(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $term = TermsOfService::create($validated);
            return response()->json(['status' => 'success', 'message' => __('Terms of service berhasil dibuat'), 'data' => ['id' => $term->id]], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat terms of service'), 'error' => $e->getMessage()], 500);
        }
    }

    public function updateTerm(Request $request, int $id): JsonResponse
    {
        try {
            $term = TermsOfService::findOrFail($id);
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $term->update($validated);
            return response()->json(['status' => 'success', 'message' => __('Terms of service berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui terms of service'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyTerm(int $id): JsonResponse
    {
        try {
            TermsOfService::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Terms of service berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus terms of service')], 500);
        }
    }

    // ── Privacy Policy ──

    public function indexPolicies(): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => PrivacyPolicy::all()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil privacy policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function showPolicy(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => PrivacyPolicy::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Privacy policy tidak ditemukan')], 404);
        }
    }

    public function storePolicy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $policy = PrivacyPolicy::create($validated);
            return response()->json(['status' => 'success', 'message' => __('Privacy policy berhasil dibuat'), 'data' => ['id' => $policy->id]], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat privacy policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePolicy(Request $request, int $id): JsonResponse
    {
        try {
            $policy = PrivacyPolicy::findOrFail($id);
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $policy->update($validated);
            return response()->json(['status' => 'success', 'message' => __('Privacy policy berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui privacy policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyPolicy(int $id): JsonResponse
    {
        try {
            PrivacyPolicy::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Privacy policy berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus privacy policy')], 500);
        }
    }

    // ── Wedding Decoration Policy ──

    public function indexWeddingPolicies(): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => WeddingDecorationPolicy::all()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil wedding decoration policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function showWeddingPolicy(int $id): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'data' => WeddingDecorationPolicy::findOrFail($id)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Wedding decoration policy tidak ditemukan')], 404);
        }
    }

    public function storeWeddingPolicy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $policy = WeddingDecorationPolicy::create($validated);
            return response()->json(['status' => 'success', 'message' => __('Wedding decoration policy berhasil dibuat'), 'data' => ['id' => $policy->id]], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal membuat wedding decoration policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function updateWeddingPolicy(Request $request, int $id): JsonResponse
    {
        try {
            $policy = WeddingDecorationPolicy::findOrFail($id);
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'nullable|array',
                'title_translations' => 'nullable|json',
                'content_translations' => 'nullable|json',
            ]);
            $policy->update($validated);
            return response()->json(['status' => 'success', 'message' => __('Wedding decoration policy berhasil diperbarui')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui wedding decoration policy'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyWeddingPolicy(int $id): JsonResponse
    {
        try {
            WeddingDecorationPolicy::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Wedding decoration policy berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus wedding decoration policy')], 500);
        }
    }
}
