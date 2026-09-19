<?php

namespace App\Http\Controllers\Api\User\SecurityController;

use App\Http\Controllers\Controller;
use App\Models\BackupCode\BackupCode;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SecurityController extends Controller
{
    public function checkup(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $items = [
            [
                'key' => 'password',
                'label_key' => 'checkupPassword',
                'description_key' => 'checkupPasswordDesc',
                'secured' => true,
                'detail_verified_key' => null,
                'detail_unverified_key' => null,
                'action_route' => '/change-password',
            ],
            [
                'key' => 'email',
                'label_key' => 'checkupEmail',
                'description' => $user->email,
                'secured' => ! empty($user->email_verified_at),
                'detail_verified_at' => $user->email_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupEmailVerified',
                'detail_unverified_key' => 'checkupEmailNotVerified',
                'action_route' => null,
            ],
            [
                'key' => 'whatsapp',
                'label_key' => 'checkupWhatsapp',
                'description' => $user->whatsapp ?: null,
                'secured' => ! empty($user->whatsapp_verified_at),
                'detail_verified_at' => $user->whatsapp_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupWhatsappVerified',
                'detail_unverified_key' => 'checkupWhatsappNotVerified',
                'action_route' => null,
            ],
            [
                'key' => 'two_factor',
                'label_key' => 'checkupTwoFactor',
                'description_key' => 'checkupTwoFactorDesc',
                'secured' => $user->two_factor_enabled ?? false,
                'detail_verified_key' => null,
                'detail_unverified_key' => 'checkupTwoFactorNotEnabled',
                'action_route' => '/two-factor-settings',
            ],
            [
                'key' => 'identity',
                'label_key' => 'checkupIdentity',
                'description_key' => 'checkupIdentityDesc',
                'secured' => ! empty($user->identity_verified_at),
                'detail_verified_at' => $user->identity_verified_at?->toISOString(),
                'detail_verified_key' => 'checkupIdentityVerified',
                'detail_unverified_key' => 'checkupIdentityNotVerified',
                'action_route' => null,
            ],
        ];

        $securedCount = collect($items)->where('secured', true)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'secured_count' => $securedCount,
                'total_items' => count($items),
            ],
        ]);
    }

    public function recentEmails(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $emails = SecurityEmail::where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $emails->items(),
            'pagination' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
            ],
        ]);
    }

    public static function recordSecurityEmail(int $userId, string $type, string $subjectKey, ?string $body = null): void
    {
        SecurityEmail::create([
            'user_id' => $userId,
            'type' => $type,
            'subject' => $subjectKey,
            'body' => $body,
            'sent_at' => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  2FA
    // ════════════════════════════════════════════════════════════════

    public function twoFactorStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'two_factor_enabled' => $user->two_factor_enabled ?? false,
                'whatsapp_number' => $user->whatsapp,
                'whatsapp_verified' => ! empty($user->whatsapp_verified_at),
                'backup_codes_remaining' => BackupCode::where('user_id', $user->id)->where('used', false)->count(),
                'trusted_devices_count' => TrustedDevice::where('user_id', $user->id)->count(),
            ],
        ]);
    }

    public function twoFactorToggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');

        if ($enabled && empty($user->whatsapp)) {
            return response()->json([
                'status' => 'error',
                'message' => 'WhatsApp number is required to enable 2FA',
            ], 422);
        }

        $user->update(['two_factor_enabled' => $enabled]);

        if (! $enabled) {
            BackupCode::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => $enabled ? '2FA enabled' : '2FA disabled',
            'data' => ['two_factor_enabled' => $enabled],
        ]);
    }

    public function generateBackupCodes(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        BackupCode::where('user_id', $user->id)->delete();

        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            BackupCode::create([
                'user_id' => $user->id,
                'code' => $code,
            ]);
            $codes[] = $code;
        }

        return response()->json([
            'status' => 'success',
            'data' => ['backup_codes' => $codes],
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  TRUSTED DEVICES
    // ════════════════════════════════════════════════════════════════

    public function trustedDevices(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $devices = TrustedDevice::where('user_id', $user->id)
            ->orderByDesc('trusted_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    public function removeTrustedDevice(int $deviceId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        TrustedDevice::where('user_id', $user->id)->where('id', $deviceId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Device removed',
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  SAVED LOGIN
    // ════════════════════════════════════════════════════════════════

    public function savedLoginStatus(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'saved_login_enabled' => $user->saved_login_enabled ?? true,
            ],
        ]);
    }

    public function savedLoginToggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user->update(['saved_login_enabled' => $request->boolean('enabled')]);

        return response()->json([
            'status' => 'success',
            'data' => ['saved_login_enabled' => $user->saved_login_enabled],
        ]);
    }
}
