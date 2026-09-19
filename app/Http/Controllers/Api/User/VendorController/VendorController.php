<?php

namespace App\Http\Controllers\Api\User\VendorController;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(): JsonResponse
    {
        $vendors = Vendor::withCount(['packages', 'products'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->has('packages')->orHas('products'))
            ->orderBy('store_name')
            ->get();

        $data = $vendors->map(fn ($v) => [
            'id'              => $v->id,
            'store_name'      => $v->store_name,
            'contact_person'  => $v->contact_person,
            'no_telp'         => $v->no_telp,
            'store_description' => $v->store_description,
            'logo'            => $v->logo ? asset('storage/'.$v->logo) : null,
            'package_count'   => (int) $v->packages_count,
            'product_count'   => (int) $v->products_count,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    public function show($id): JsonResponse
    {
        $vendor = Vendor::withCount(['packages', 'products'])->findOrFail($id);

        $packages = $vendor->packages()->with(['category', 'media'])->get();
        $products = $vendor->products()->with(['category', 'media'])->get();

        $data = [
            'id'              => $vendor->id,
            'store_name'      => $vendor->store_name,
            'contact_person'  => $vendor->contact_person,
            'no_telp'         => $vendor->no_telp,
            'store_description' => $vendor->store_description,
            'logo'            => $vendor->logo ? asset('storage/'.$vendor->logo) : null,
            'package_count'   => (int) $vendor->packages_count,
            'product_count'   => (int) $vendor->products_count,
            'packages'        => $packages,
            'products'        => $products,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }
}
