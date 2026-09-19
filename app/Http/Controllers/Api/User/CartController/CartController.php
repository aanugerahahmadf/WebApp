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
            ->with(['product', 'package'])
            ->latest()
            ->get();

        $items->transform(function ($item) use ($locale) {
            if ($item->product) {
                $item->product->name = $item->product->trans('name', $locale);
                $item->product->description = $item->product->trans('description', $locale);
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
            'product_id' => 'nullable|exists:products,id',
            'package_id' => 'nullable|exists:packages,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $validated['user_id'] = $request->user()->id;

        // ── Stock validation ──
        if (isset($validated['product_id'])) {
            $product = \App\Models\Product\Product::find($validated['product_id']);
            if ($product && $product->stock < $validated['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok produk tidak mencukupi. Stok tersedia: ' . $product->stock,
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
                if (isset($validated['product_id'])) {
                    $q->where('product_id', $validated['product_id']);
                }
                if (isset($validated['package_id'])) {
                    $q->where('package_id', $validated['package_id']);
                }
            })->first();

        $locale = app()->getLocale();

        if ($existing) {
            $existing->increment('quantity', $validated['quantity']);
            $item = $existing->fresh()->load(['product', 'package']);
        } else {
            $item = Cart::create($validated);
            $item->load(['product', 'package']);
        }

        if ($item->product) {
            $item->product->name = $item->product->trans('name', $locale);
            $item->product->description = $item->product->trans('description', $locale);
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
        $item = $cart->product ?? $cart->package;
        if ($item && $item->stock < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi. Stok tersedia: ' . $item->stock,
            ], 422);
        }

        $cart->update($validated);

        $item = $cart->fresh()->load(['product', 'package']);

        if ($item->product) {
            $item->product->name = $item->product->trans('name', $locale);
            $item->product->description = $item->product->trans('description', $locale);
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
