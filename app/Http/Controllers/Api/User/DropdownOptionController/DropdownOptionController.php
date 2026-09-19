<?php

namespace App\Http\Controllers\Api\User\DropdownOptionController;

use App\Http\Controllers\Controller;
use App\Models\ReferenceOption\ReferenceOption;
use Illuminate\Http\Request;

class DropdownOptionController extends Controller
{
    public function index(Request $request)
    {
        // Priority: query param > Accept-Language header > app default
        $locale = $request->get(
            'locale',
            $request->header('Accept-Language', app()->getLocale())
        );
        // Normalize: take just the language code
        $locale = substr($locale, 0, 2);

        $options = ReferenceOption::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type')
            ->map(function ($items) use ($locale) {
                return $items->map(function (ReferenceOption $item) use ($locale) {
                    return [
                        'key' => $item->key,
                        'label' => $item->getLabelForLocale($locale),
                    ];
                })->values();
            });

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }
}
