<?php

namespace App\Http\Controllers\User\PusherAuthController;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PusherAuthController extends Controller
{
    public function auth(Request $request): JsonResponse
    {
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        if (! $socketId || ! $channelName) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        $user = $request->user();
        if (str_starts_with($channelName, 'private-') || str_starts_with($channelName, 'presence-')) {
            if (! $user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $key = (string) (config('broadcasting.connections.pusher.key') ?? env('PUSHER_APP_KEY'));
        $secret = (string) (config('broadcasting.connections.pusher.secret') ?? env('PUSHER_APP_SECRET'));

        if ($key === '' || $secret === '') {
            return response()->json(['error' => 'Broadcasting is not configured'], 500);
        }

        $stringToSign = $socketId.':'.$channelName;
        $signature = hash_hmac('sha256', $stringToSign, $secret);
        $auth = $key.':'.$signature;

        $response = ['auth' => $auth];

        if (str_starts_with($channelName, 'presence-') && $user) {
            $channelData = [
                'user_id' => (string) $user->getAttribute('id'),
                'user_info' => [
                    'name' => $user->getAttribute('full_name') ?? $user->getAttribute('name'),
                ],
            ];
            $response['channel_data'] = json_encode($channelData);
        }

        return response()->json($response);
    }
}
