<?php

namespace App\Http\Controllers\Api\Admin\MessageController;

use App\Http\Controllers\Controller;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function inboxes(Request $request): JsonResponse
    {
        try {
            $query = Inbox::withCount('messages');

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where('title', 'like', "%{$s}%");
                $userIds = User::where('full_name', 'like', "%{$s}%")->pluck('id');
                if ($userIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($userIds): void {
                        foreach ($userIds as $uid) {
                            $q->orWhereJsonContains('user_ids', $uid);
                        }
                    });
                }
            }

            $inboxes = $query->orderBy('updated_at', 'desc')->paginate($request->get('per_page', 20));

            $data = collect($inboxes->items())->map(function ($inbox) {
                $userIds = collect($inbox->user_ids);
                $users = User::whereIn('id', $userIds)->get(['id', 'full_name', 'username', 'email', 'avatar_url'])
                    ->map(function (User $user): array {
                        return [
                            'id' => $user->id,
                            'full_name' => $user->full_name,
                            'username' => $user->username,
                            'email' => $user->email,
                            'avatar_url' => $user->avatar_url,
                            'is_guest' => is_string($user->email) && str_ends_with($user->email, '@guest.local'),
                        ];
                    });
                $lastMessage = $inbox->latestMessage();

                return [
                    'id' => $inbox->id,
                    'title' => $inbox->title,
                    'meta' => $inbox->meta,
                    'guest_id' => $inbox->meta['guest_id'] ?? null,
                    'participants' => $users,
                    'messages_count' => $inbox->messages_count,
                    'last_message' => $lastMessage ? [
                        'message' => $lastMessage->message,
                        'sender_id' => $lastMessage->user_id,
                        'created_at' => $lastMessage->created_at?->toISOString(),
                    ] : null,
                    'created_at' => $inbox->created_at?->toISOString(),
                    'updated_at' => $inbox->updated_at?->toISOString(),
                ];
            });

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $inboxes->currentPage(), 'last_page' => $inboxes->lastPage(),
                'per_page' => $inboxes->perPage(), 'total' => $inboxes->total(),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengambil percakapan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function showInbox(int $id): JsonResponse
    {
        try {
            $inbox = Inbox::with('messages.sender:id,full_name,avatar_url')->findOrFail($id);
            $userIds = collect($inbox->user_ids);
            $users = User::whereIn('id', $userIds)->get(['id', 'full_name', 'username', 'email', 'avatar_url'])
                ->map(function (User $user): array {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'avatar_url' => $user->avatar_url,
                        'is_guest' => is_string($user->email) && str_ends_with($user->email, '@guest.local'),
                    ];
                });

            return response()->json(['status' => 'success', 'data' => [
                'id' => $inbox->id,
                'title' => $inbox->title,
                'meta' => $inbox->meta,
                'guest_id' => $inbox->meta['guest_id'] ?? null,
                'participants' => $users,
                'messages' => $inbox->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'user_id' => $m->user_id,
                    'sender_name' => $m->sender?->full_name,
                    'message' => $m->message,
                    'meta' => $m->meta,
                    'created_at' => $m->created_at?->toISOString(),
                ]),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Percakapan tidak ditemukan')], 404);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'inbox_id' => 'required|integer|exists:fm_inboxes,id',
                'message' => 'required|string',
            ]);

            $inbox = Inbox::findOrFail($validated['inbox_id']);
            $msg = Message::create([
                'inbox_id' => $inbox->id,
                'message' => $validated['message'],
                'user_id' => $request->user()->id,
            ]);

            $inbox->touch();

            return response()->json(['status' => 'success', 'message' => __('Pesan berhasil dikirim'), 'data' => [
                'id' => $msg->id,
                'inbox_id' => $msg->inbox_id,
                'user_id' => $msg->user_id,
                'message' => $msg->message,
                'meta' => $msg->meta,
                'created_at' => $msg->created_at?->toISOString(),
            ]], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal mengirim pesan'), 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyInbox(int $id): JsonResponse
    {
        try {
            Inbox::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Percakapan berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus percakapan')], 500);
        }
    }

    public function destroyMessage(int $id): JsonResponse
    {
        try {
            Message::findOrFail($id)->delete();

            return response()->json(['status' => 'success', 'message' => __('Pesan berhasil dihapus')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => __('Gagal menghapus pesan')], 500);
        }
    }
}
