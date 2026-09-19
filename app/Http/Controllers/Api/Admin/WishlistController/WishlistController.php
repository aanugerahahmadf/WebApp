<?php

namespace App\Http\Controllers\Api\Admin\WishlistController;

use App\Http\Controllers\Controller;
use App\Models\Wishlist\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Wishlist::with('user:id,full_name,email', 'package:id,name,price', 'product:id,name,price');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('package_id')) {
                $query->where('package_id', $request->package_id);
            }

            $wishlists = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

            $data = collect($wishlists->items())->map(fn ($w) => [
                'id' => $w->id,
                'user_id' => $w->user_id,
                'user_name' => $w->user?->full_name,
                'package_id' => $w->package_id,
                'package_name' => $w->package?->name,
                'product_id' => $w->product_id,
                'product_name' => $w->product?->name,
                'created_at' => $w->created_at?->toISOString(),
                'updated_at' => $w->updated_at?->toISOString(),
            ]);

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $wishlists->currentPage(), 'last_page' => $wishlists->lastPage(),
                'per_page' => $wishlists->perPage(), 'total' => $wishlists->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil wishlist'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Wishlist::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Wishlist berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus wishlist')], 500);
        }
    }
}
