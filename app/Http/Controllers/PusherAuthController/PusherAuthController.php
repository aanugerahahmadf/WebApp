<?php

namespace App\Http\Controllers\PusherAuthController;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class PusherAuthController extends Controller
{
    /**
     * Handle Pusher private/presence auth
     */
    public function auth(Request $request)
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

        $key = env('PUSHER_APP_KEY');
        $secret = env('PUSHER_APP_SECRET');
        $appId = env('PUSHER_APP_ID');

        // Build auth signature manually to avoid requiring an SDK here
        $stringToSign = $socketId.':'.$channelName;
        $signature = hash_hmac('sha256', $stringToSign, $secret);
        $auth = $key.':'.$signature;

        $response = ['auth' => $auth];

        if (str_starts_with($channelName, 'presence-')) {
            $channelData = [
                'user_id' => (string) $user->id,
                'user_info' => [
                    'name' => $user->name ?? null,
                ],
            ];
            $response['channel_data'] = json_encode($channelData);
        }

        return response()->json($response);
    }
}
