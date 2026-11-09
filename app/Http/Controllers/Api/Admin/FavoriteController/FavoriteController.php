<?php

namespace App\Http\Controllers\Api\Admin\FavoriteController;

use App\Http\Controllers\Controller;
use App\Models\Favorite\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Favorite::with('user:id,full_name,email', 'package:id,name,price', 'catalog:id,name,price');

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('package_id')) {
                $query->where('package_id', $request->package_id);
            }

            $Favorites = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

            $data = collect($Favorites->items())->map(fn ($w) => [
                'id' => $w->id,
                'user_id' => $w->user_id,
                'user_name' => $w->user?->full_name,
                'package_id' => $w->package_id,
                'package_name' => $w->package?->name,
                'catalog_id' => $w->catalog_id,
                'catalog_name' => $w->catalog?->name,
                'created_at' => $w->created_at?->toISOString(),
                'updated_at' => $w->updated_at?->toISOString(),
            ]);

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $Favorites->currentPage(), 'last_page' => $Favorites->lastPage(),
                'per_page' => $Favorites->perPage(), 'total' => $Favorites->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil Favorite'), 'error' => null], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Favorite::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Favorite berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus Favorite')], 500);
        }
    }
}
