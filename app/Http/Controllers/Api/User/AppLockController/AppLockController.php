<?php

namespace App\Http\Controllers\Api\User\AppLockController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Services\FaceService\FaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AppLockController extends Controller
{
    /**
     * GET /profile/app-lock — Get current user's app lock settings.
     * Returns full status including whether lock is enabled and expired.
     */
    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data' => array_merge($user->getAppLockStatus(), [
                'face_enrolled_at' => $user->app_lock_face_enrolled_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * GET /profile/app-lock/status — Quick check if app lock screen should be shown.
     * Used by the mobile app on cold start / resume to decide whether to lock.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $timeout = $request->integer('timeout_minutes', 5);

        return response()->json([
            'status' => 'success',
            'data' => [
                'lock_required' => $user->isAppLockEnabled() && $user->isAppLockExpired($timeout),
                'lock_enabled' => $user->isAppLockEnabled(),
                'methods' => array_filter([
                    $user->app_lock_fingerprint_enabled ? 'fingerprint' : null,
                    $user->app_lock_face_enabled ? 'face' : null,
                    $user->app_lock_pin_enabled ? 'pin' : null,
                ]),
                'last_unlock_at' => $user->app_lock_last_unlock_at?->toIso8601String(),
                'timeout_minutes' => $timeout,
            ],
        ]);
    }

    /**
     * PUT /profile/app-lock — Update app lock settings.
     *
     * Accepts: fingerprint_enabled, face_enabled, pin_enabled
     * When disabling face, also clears enrollment data.
     * When disabling PIN, also clears the stored hash.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'fingerprint_enabled' => 'sometimes|boolean',
            'face_enabled' => 'sometimes|boolean',
            'pin_enabled' => 'sometimes|boolean',
        ]);

        // When disabling PIN, also clear the stored hash.
        if (isset($validated['pin_enabled']) && ! $validated['pin_enabled']) {
            $validated['app_lock_pin_hash'] = null;
        }

        // When disabling face, also clear enrollment data.
        if (isset($validated['face_enabled']) && ! $validated['face_enabled']) {
            // Delete the stored face reference file
            if ($user->app_lock_face_reference && Storage::disk('public')->exists($user->app_lock_face_reference)) {
                Storage::disk('public')->delete($user->app_lock_face_reference);
            }
            $validated['app_lock_face_enrolled'] = false;
            $validated['app_lock_face_reference'] = null;
            $validated['app_lock_face_enrolled_at'] = null;
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Pengaturan kunci aplikasi diperbarui'),
            'data' => $user->getAppLockStatus(),
        ]);
    }

    /**
     * POST /profile/app-lock/pin — Set or change the app lock PIN.
     *
     * Accepts: current_pin (required if PIN already set), pin (6 digits)
     */
    public function setPin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'pin' => 'required|string|size:6|digits:6',
            'current_pin' => [
                $user->app_lock_pin_hash ? 'required' : 'nullable',
                'string',
                'size:6',
            ],
        ]);

        if (! empty($validated['current_pin']) && ! Hash::check($validated['current_pin'], (string) ($user->app_lock_pin_hash ?? ''))) {
            return response()->json([
                'status' => 'error',
                'message' => __('PIN lama tidak sesuai'),
            ], 422);
        }

        $user->app_lock_pin_hash = Hash::make($validated['pin']);
        $user->app_lock_pin_enabled = true;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('PIN kunci aplikasi berhasil disimpan'),
            'data' => [
                'pin_enabled' => true,
            ],
        ]);
    }

    /**
     * POST /profile/app-lock/pin/verify — Verify PIN (used by app lock screen).
     */
    public function verifyPin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'pin' => 'required|string|size:6|digits:6',
        ]);

        $valid = $user->app_lock_pin_hash && Hash::check($validated['pin'], $user->app_lock_pin_hash);

        if ($valid) {
            $user->app_lock_last_unlock_at = now();
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'verified' => $valid,
            ],
        ]);
    }

    /**
     * POST /profile/app-lock/face-enroll — Enroll face for Face ID app lock.
     *
     * Accepts: face_photo (file), liveness_completed (bool)
     * Verifies face against the user's KYC selfie/photo via AI Core
     * to ensure the person enrolling is the same as the verified user.
     *
     * NOTE: Ini berbeda dari Verifikasi Wajah KYC. KYC menyimpan selfie_photo
     * dan face_scan_photo untuk verifikasi identitas. App Lock Face menyimpan
     * app_lock_face_reference secara terpisah untuk keperluan membuka kunci
     * aplikasi. Keduanya menggunakan jalur data yang terpisah.
     */
    public function faceEnroll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'face_photo' => 'required|image|mimes:jpg,jpeg,png|max:10240',
            'liveness_completed' => 'required|boolean',
        ]);

        if (! $validated['liveness_completed']) {
            return response()->json([
                'status' => 'error',
                'message' => __('Verifikasi liveness belum selesai'),
            ], 422);
        }

        $photo = $request->file('face_photo');
        $tmpPath = $photo->getRealPath();

        if (! $tmpPath || ! file_exists($tmpPath)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Foto wajah tidak dapat diproses'),
            ], 422);
        }

        // Reference: user's KYC selfie or face scan
        $referencePath = null;
        if ($user->selfie_photo) {
            $referencePath = Storage::disk('public')->path($user->selfie_photo);
        } elseif ($user->face_scan_photo) {
            $referencePath = Storage::disk('public')->path($user->face_scan_photo);
        } elseif ($user->ktp_photo) {
            $referencePath = Storage::disk('public')->path($user->ktp_photo);
        }

        if (! $referencePath || ! file_exists($referencePath)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Verifikasi wajah (KYC) belum diselesaikan. Silakan verifikasi identitas terlebih dahulu sebelum mendaftarkan wajah untuk kunci aplikasi.'),
                'data' => [
                    'verified' => false,
                    'reason' => 'KYC_NOT_COMPLETED',
                ],
            ], 422);
        }

        $faceService = new FaceService;
        $result = $faceService->verifyFace($tmpPath, $referencePath);

        if (! ($result['success'] ?? false) || ! ($result['verified'] ?? false)) {
            Log::warning('[AppLock] Face enrollment failed', [
                'user_id' => $user->id,
                'result' => $result,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $result['reason'] ?? __('Wajah tidak cocok dengan data KYC. Pastikan wajah yang didaftarkan untuk kunci aplikasi sesuai dengan verifikasi identitas.'),
                'data' => [
                    'verified' => false,
                    'similarity' => $result['similarity'] ?? 0,
                    'reason' => $result['reason'] ?? 'FACE_MISMATCH',
                ],
            ], 422);
        }

        // Delete old face reference file if exists
        if ($user->app_lock_face_reference && Storage::disk('public')->exists($user->app_lock_face_reference)) {
            Storage::disk('public')->delete($user->app_lock_face_reference);
        }

        // Save enrolled face photo
        $filename = 'app_lock_face_'.$user->id.'_'.time().'.jpg';
        $relativePath = 'app_lock/faces/'.$filename;
        Storage::disk('public')->put($relativePath, file_get_contents($tmpPath));

        $user->app_lock_face_enrolled = true;
        $user->app_lock_face_enabled = true;
        $user->app_lock_face_reference = $relativePath;
        $user->app_lock_face_enrolled_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Wajah berhasil didaftarkan untuk kunci aplikasi'),
            'data' => [
                'face_enrolled' => true,
                'face_enabled' => true,
                'similarity' => $result['similarity'] ?? 0,
                'enrolled_at' => $user->app_lock_face_enrolled_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /profile/app-lock/face-verify — Verify face for app lock unlock.
     *
     * Accepts: face_photo (file)
     * Compares against the enrolled app_lock_face_reference (bukan KYC selfie)
     * via AI Core. Hanya menggunakan foto yang tersimpan di app_lock/faces/.
     */
    public function faceVerify(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'face_photo' => 'required|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        if (! $user->app_lock_face_enrolled || ! $user->app_lock_face_reference) {
            return response()->json([
                'status' => 'error',
                'message' => __('Wajah belum didaftarkan untuk kunci aplikasi'),
            ], 422);
        }

        $facePhoto = $request->file('face_photo');
        $tmpPath = $facePhoto->getRealPath();

        if (! $tmpPath || ! file_exists($tmpPath)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Foto wajah tidak dapat diproses'),
            ], 422);
        }

        $referencePath = Storage::disk('public')->path($user->app_lock_face_reference);
        if (! file_exists($referencePath)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Referensi wajah tidak ditemukan'),
            ], 500);
        }

        $faceService = new FaceService;
        $result = $faceService->verifyFace($tmpPath, $referencePath);

        $verified = ($result['success'] ?? false) && ($result['verified'] ?? false);

        if ($verified) {
            $user->app_lock_last_unlock_at = now();
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'verified' => $verified,
                'similarity' => $result['similarity'] ?? 0,
                'reason' => $result['reason'] ?? null,
            ],
        ]);
    }

    /**
     * POST /admin/users/{id}/app-lock/reset — Admin reset a user's app lock.
     *
     * Disables all lock methods and clears PIN + face enrollment.
     */
    public function adminReset(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Delete the stored face reference file
        if ($user->app_lock_face_reference && Storage::disk('public')->exists($user->app_lock_face_reference)) {
            Storage::disk('public')->delete($user->app_lock_face_reference);
        }

        $user->app_lock_fingerprint_enabled = false;
        $user->app_lock_face_enabled = false;
        $user->app_lock_pin_enabled = false;
        $user->app_lock_pin_hash = null;
        $user->app_lock_face_enrolled = false;
        $user->app_lock_face_reference = null;
        $user->app_lock_face_enrolled_at = null;
        $user->app_lock_last_unlock_at = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => __('Kunci aplikasi berhasil direset'),
        ]);
    }
}
