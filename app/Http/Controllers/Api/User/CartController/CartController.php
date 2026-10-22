<?php

namespace App\Http\Controllers\Api\User\CartController;

use App\Http\Controllers\Controller;
use App\Models\Cart\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();
        $items = Cart::where('user_id', $request->user()->id)
            ->with(['catalog', 'package'])
            ->latest()
            ->get();

        $items->transform(function ($item) use ($locale) {
            if ($item->catalog) {
                $item->catalog->name = $item->catalog->trans('name', $locale);
                $item->catalog->description = $item->catalog->trans('description', $locale);
            }
            if ($item->package) {
                $item->package->name = $item->package->trans('name', $locale);
                $item->package->description = $item->package->trans('description', $locale);
            }
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'catalog_id' => 'nullable|exists:catalogs,id',
            'package_id' => 'nullable|exists:packages,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $validated['user_id'] = $request->user()->id;

        // ── Stock validation ──
        if (isset($validated['catalog_id'])) {
            $catalog = \App\Models\Catalog\Catalog::find($validated['catalog_id']);
            if ($catalog && $catalog->stock < $validated['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok katalog tidak mencukupi. Stok tersedia: ' . $catalog->stock,
                ], 422);
            }
        }
        if (isset($validated['package_id'])) {
            $package = \App\Models\Package\Package::find($validated['package_id']);
            if ($package && $package->stock < $validated['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok paket tidak mencukupi. Stok tersedia: ' . $package->stock,
                ], 422);
            }
        }

        $existing = Cart::where('user_id', $request->user()->id)
            ->where(function ($q) use ($validated) {
                if (isset($validated['catalog_id'])) {
                    $q->where('catalog_id', $validated['catalog_id']);
                }
                if (isset($validated['package_id'])) {
                    $q->where('package_id', $validated['package_id']);
                }
            })->first();

        $locale = app()->getLocale();

        if ($existing) {
            $existing->increment('quantity', $validated['quantity']);
            $item = $existing->fresh()->load(['catalog', 'package']);
        } else {
            $item = Cart::create($validated);
            $item->load(['catalog', 'package']);
        }

        if ($item->catalog) {
            $item->catalog->name = $item->catalog->trans('name', $locale);
            $item->catalog->description = $item->catalog->trans('description', $locale);
        }
        if ($item->package) {
            $item->package->name = $item->package->trans('name', $locale);
            $item->package->description = $item->package->trans('description', $locale);
        }

        return response()->json(['status' => 'success', 'data' => $item], $existing ? 200 : 201);
    }

    public function update(Request $request, Cart $cart): JsonResponse
    {
        $locale = app()->getLocale();

        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['quantity' => 'required|integer|min:1']);

        // ── Stock validation ──
        $item = $cart->catalog ?? $cart->package;
        if ($item && $item->stock < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi. Stok tersedia: ' . $item->stock,
            ], 422);
        }

        $cart->update($validated);

        $item = $cart->fresh()->load(['catalog', 'package']);

        if ($item->catalog) {
            $item->catalog->name = $item->catalog->trans('name', $locale);
            $item->catalog->description = $item->catalog->trans('description', $locale);
        }
        if ($item->package) {
            $item->package->name = $item->package->trans('name', $locale);
            $item->package->description = $item->package->trans('description', $locale);
        }

        return response()->json(['status' => 'success', 'data' => $item]);
    }

    public function destroy(Request $request, Cart $cart): JsonResponse
    {
        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $cart->delete();

        return response()->json(['status' => 'success', 'message' => 'Item removed from cart']);
    }
}
