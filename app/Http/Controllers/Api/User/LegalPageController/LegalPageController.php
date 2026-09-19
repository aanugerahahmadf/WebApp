<?php

namespace App\Http\Controllers\Api\User\LegalPageController;

use App\Http\Controllers\Controller;
use App\Models\LegalPage\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = LegalPage::query();

            if ($request->filled('slug')) {
                $query->where('slug', $request->slug);
            }

            $pages = $query->paginate($request->get('per_page', 50));

            return response()->json([
                'status' => 'success',
                'data' => $pages->items(),
                'pagination' => [
                    'current_page' => $pages->currentPage(),
                    'last_page' => $pages->lastPage(),
                    'per_page' => $pages->perPage(),
                    'total' => $pages->total(),
                    'has_more_pages' => $pages->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil halaman legal'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($slug)
    {
        try {
            $page = LegalPage::where('slug', $slug)->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $page,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Halaman tidak ditemukan'),
            ], 404);
        }
    }
}
