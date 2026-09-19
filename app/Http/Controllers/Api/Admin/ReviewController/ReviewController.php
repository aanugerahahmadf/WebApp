<?php

namespace App\Http\Controllers\Api\Admin\ReviewController;

use App\Http\Controllers\Controller;
use App\Models\Review\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Review::with('user:id,full_name,email,avatar_url', 'package:id,name', 'product:id,name');
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s): void {
                    $q->where('comment', 'like', "%{$s}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('full_name', 'like', "%{$s}%"));
                });
            }
            if ($request->filled('rating')) {
                $query->where('rating', $request->rating);
            }
            if ($request->filled('package_id')) {
                $query->where('package_id', $request->package_id);
            }
            $reviews = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));
            $data = collect($reviews->items())->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'user_name' => $r->user?->full_name,
                'user_avatar' => $r->user?->avatar_url,
                'package_id' => $r->package_id,
                'product_id' => $r->product_id,
                'item_name' => $r->package?->name ?? $r->product?->name,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->toISOString(),
                'updated_at' => $r->updated_at?->toISOString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil ulasan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $review = Review::with('user:id,full_name,email', 'package:id,name', 'product:id,name')->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $review]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Ulasan tidak ditemukan')], 404);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);
            $validated = $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'nullable|string',
            ]);
            $review->update($validated);

            return response()->json(['status' => 'success', 'message' => __('Ulasan berhasil diperbarui')]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal memperbarui ulasan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $review = Review::findOrFail($id);
            $review->delete();

            return response()->json(['status' => 'success', 'message' => __('Ulasan berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus ulasan')], 500);
        }
    }
}
