<?php

namespace App\Http\Controllers\Api\User\UserLanguageController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\UserLanguage\UserLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLanguageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $lang = UserLanguage::where('model_id', $user->id)
            ->where('model_type', User::class)
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'lang' => $lang?->lang ?? 'id',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'required|string|max:10',
        ]);

        $user = $request->user();

        UserLanguage::updateOrCreate(
            [
                'model_id' => $user->id,
                'model_type' => User::class,
            ],
            ['lang' => $request->lang]
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Bahasa berhasil diperbarui'),
            'data' => ['lang' => $request->lang],
        ]);
    }
}
