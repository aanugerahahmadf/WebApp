<?php

namespace App\Http\Controllers\Api\User\LoginActivityController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\UserSession\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $sessions = UserSession::where('user_id', $user->id)
            ->where('expired_at', null)
            ->orderByDesc('last_active_at')
            ->get()
            ->map(function ($session) use ($currentTokenId) {
                return [
                    'id' => $session->id,
                    'device_name' => $session->device_name ?? 'Unknown Device',
                    'device_type' => $session->device_type ?? 'unknown',
                    'ip_address' => $session->ip_address,
                    'platform' => $session->platform,
                    'last_active_at' => $session->last_active_at?->toISOString(),
                    'is_current' => $session->id === $currentTokenId || $session->is_current,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'current' => $sessions->firstWhere('is_current', true),
                'others' => $sessions->where('is_current', false)->values(),
            ],
        ]);
    }

    public function destroy(int $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $session = UserSession::where('user_id', $user->id)
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return response()->json(['status' => 'error', 'message' => 'Session not found'], 404);
        }

        if ($session->is_current) {
            return response()->json(['status' => 'error', 'message' => 'Cannot remove current session'], 422);
        }

        $session->update(['expired_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session removed',
        ]);
    }

    public static function recordSession(int $userId, string $deviceName, string $deviceType, string $ipAddress, string $userAgent, string $platform): void
    {
        UserSession::updateOrCreate(
            [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ],
            [
                'device_name' => $deviceName,
                'device_type' => $deviceType,
                'user_agent' => $userAgent,
                'platform' => $platform,
                'is_current' => true,
                'last_active_at' => now(),
            ]
        );
    }
}
