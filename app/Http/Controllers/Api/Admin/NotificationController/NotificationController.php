<?php

namespace App\Http\Controllers\Api\Admin\NotificationController;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = \Illuminate\Notifications\DatabaseNotification::query()->with('notifiable:id,full_name,email');

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where('data', 'like', "%{$s}%");
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('read')) {
                $request->boolean('read') ? $query->whereNotNull('read_at') : $query->whereNull('read_at');
            }
            if ($request->filled('user_id')) {
                $query->where('notifiable_id', $request->user_id)->where('notifiable_type', User::class);
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

            $data = collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'notifiable_id' => $n->notifiable_id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toISOString(),
                'created_at' => $n->created_at?->toISOString(),
            ]);

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(), 'total' => $notifications->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil notifikasi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $notification = \Illuminate\Notifications\DatabaseNotification::findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $notification]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Notifikasi tidak ditemukan')], 404);
        }
    }

    public function sendToUser(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string',
                'type' => 'nullable|string|max:100',
            ]);

            $user = User::findOrFail($validated['user_id']);
            $user->notify(new \App\Notifications\CustomNotification\CustomNotification(
                $validated['title'],
                $validated['body'],
                $validated['type'] ?? 'admin'
            ));

            return response()->json(['status' => 'success', 'message' => __('Notifikasi berhasil dikirim')], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengirim notifikasi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function sendBulk(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string',
                'type' => 'nullable|string|max:100',
            ]);

            $users = User::whereIn('id', $validated['user_ids'])->get();
            $count = 0;
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\CustomNotification\CustomNotification(
                    $validated['title'],
                    $validated['body'],
                    $validated['type'] ?? 'admin'
                ));
                $count++;
            }

            return response()->json(['status' => 'success', 'message' => __("{$count} notifikasi berhasil dikirim")], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengirim notifikasi'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            \Illuminate\Notifications\DatabaseNotification::findOrFail($id)->delete();
            return response()->json(['status' => 'success', 'message' => __('Notifikasi berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus notifikasi')], 500);
        }
    }
}
