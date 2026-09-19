<?php

namespace App\Http\Controllers\Api\User\NotificationController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Models\UserFcmDevice\UserFcmDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function index(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $notifications = $user->notifications()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Notification] index error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil notifikasi'),
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $notification = $user->notifications()->findOrFail($id);
            $notification->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => __('Notifikasi ditandai sebagai sudah dibaca'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menandai notifikasi sebagai sudah dibaca'),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $user->unreadNotifications()->update(['read_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => __('Semua notifikasi ditandai sebagai sudah dibaca'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menandai semua notifikasi sebagai sudah dibaca'),
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $notification = $user->notifications()->findOrFail($id);
            $notification->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Notifikasi berhasil dihapus'),
            ]);
        } catch (\Exception $e) {
            Log::error('[Notification] destroy error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus notifikasi'),
            ], 500);
        }
    }

    /**
     * Register FCM token for push notifications
     */
    public function registerFcmToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'platform' => 'nullable|string|in:android,ios',
                'device_name' => 'nullable|string|max:255',
            ]);

            /** @var User $user */
            $user = Auth::user();

            UserFcmDevice::updateOrCreate(
                ['user_id' => $user->id, 'fcm_token' => $request->token],
                [
                    'platform' => $request->platform ?? 'android',
                    'device_name' => $request->device_name,
                    'last_used_at' => now(),
                ]
            );

            $user->update(['fcm_token' => $request->token]);

            return response()->json([
                'status' => 'success',
                'message' => __('Token FCM berhasil didaftarkan'),
            ]);
        } catch (\Exception $e) {
            Log::error('[Notification] registerFcmToken error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mendaftarkan token FCM'),
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount()
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'count' => $user->unreadNotifications()->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil jumlah notifikasi belum dibaca'),
            ], 500);
        }
    }
}
