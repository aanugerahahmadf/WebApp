<?php

namespace App\Http\Controllers\ChatCall;

use App\Http\Controllers\Controller;
use App\Models\ChatCall\ChatCall;
use App\Models\Inbox\Inbox;
use App\Models\User\User;
use App\Services\GuestIdentity\GuestIdentity;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatCallController extends Controller
{
    public function start(Request $request)
    {
        $request->validate(['inbox_id' => 'required|integer', 'guest_id' => 'nullable|string|max:190']);
        $actor = $this->actor($request);
        $inbox = Inbox::findOrFail($request->integer('inbox_id'));
        $this->authorizeParticipant($inbox, $actor);
        $calleeId = collect($inbox->user_ids)->map(fn ($id) => (int) $id)->first(fn ($id) => $id !== (int) $actor->id);
        abort_unless($calleeId, 422, 'Tidak ada peserta panggilan.');

        ChatCall::where('inbox_id', $inbox->id)->where('status', 'ringing')->update(['status' => 'ended', 'ended_at' => now()]);
        $call = ChatCall::create([
            'token' => (string) Str::uuid(),
            'inbox_id' => $inbox->id,
            'caller_user_id' => $actor->id,
            'callee_user_id' => $calleeId,
            'expires_at' => now()->addMinutes(2),
        ]);
        return response()->json($this->payload($call, $actor));
    }

    public function active(Request $request, int $inboxId)
    {
        $actor = $this->actor($request);
        $inbox = Inbox::findOrFail($inboxId);
        $this->authorizeParticipant($inbox, $actor);
        $call = ChatCall::where('inbox_id', $inboxId)
            ->whereIn('status', ['ringing', 'connecting', 'connected'])
            ->where('expires_at', '>', now())
            ->latest('id')->first();
        return response()->json(['success' => true, 'call' => $call ? $this->payload($call, $actor)['call'] : null]);
    }

    public function state(Request $request, string $token)
    {
        $call = ChatCall::where('token', $token)->firstOrFail();
        $actor = $this->actor($request);
        $this->authorizeCall($call, $actor);
        if ($call->status === 'ringing' && $call->expires_at->isPast()) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
        }
        return response()->json($this->payload($call->fresh(), $actor));
    }

    public function signal(Request $request, string $token)
    {
        $request->validate(['type' => 'required|in:offer,answer,ice', 'value' => 'required|array']);
        $call = ChatCall::where('token', $token)->firstOrFail();
        $actor = $this->actor($request);
        $this->authorizeCall($call, $actor);
        $field = match ($request->string('type')->toString()) {
            'offer' => 'offer', 'answer' => 'answer', default => 'ice_candidates',
        };
        if ($field === 'ice_candidates') {
            $call->{$field} = array_values(array_merge($call->ice_candidates ?? [], [$request->input('value')]));
        } else {
            $call->{$field} = $request->input('value');
        }
        $call->status = $call->status === 'ringing' ? 'connecting' : $call->status;
        $call->save();
        return response()->json($this->payload($call, $actor));
    }

    public function accept(Request $request, string $token)
    {
        $call = ChatCall::where('token', $token)->firstOrFail();
        $actor = $this->actor($request);
        $this->authorizeCall($call, $actor);
        abort_unless((int) $actor->id === (int) $call->callee_user_id, 403);
        $call->update(['status' => 'connecting']);
        return response()->json($this->payload($call, $actor));
    }

    public function end(Request $request, string $token)
    {
        $call = ChatCall::where('token', $token)->firstOrFail();
        $actor = $this->actor($request);
        $this->authorizeCall($call, $actor);
        $call->update(['status' => 'ended', 'ended_at' => now()]);
        return response()->json(['success' => true]);
    }

    private function actor(Request $request): User
    {
        $user = Auth::user() ?? Auth::guard('sanctum')->user() ?? (Auth::guard('admin')->check() ? ChatStaff::adminUser() : null);
        if ($user instanceof User) return $user;
        $guestId = $request->input('guest_id') ?: $request->cookie(GuestIdentity::COOKIE);
        abort_unless(is_string($guestId) && $guestId !== '', 401);
        return GuestIdentity::resolve($guestId);
    }

    private function authorizeParticipant(Inbox $inbox, User $actor): void
    { abort_unless(in_array((int) $actor->id, array_map('intval', $inbox->user_ids ?? []), true), 403); }

    private function authorizeCall(ChatCall $call, User $actor): void
    { abort_unless(in_array((int) $actor->id, [(int) $call->caller_user_id, (int) $call->callee_user_id], true), 403); }

    private function payload(ChatCall $call, User $actor): array
    {
        return ['success' => true, 'call' => [
            'token' => $call->token, 'status' => $call->status,
            'role' => (int) $actor->id === (int) $call->caller_user_id ? 'caller' : 'callee',
            'offer' => $call->offer, 'answer' => $call->answer,
            'ice_candidates' => $call->ice_candidates ?? [],
            'expires_at' => $call->expires_at?->toIso8601String(),
        ]];
    }
}
