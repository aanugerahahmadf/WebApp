<?php

namespace App\Http\Controllers\Api\User\PaymentWebhookController;

use App\Http\Controllers\Controller;

use App\Services\MidtransService\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function notification(Request $request, MidtransService $midtrans): JsonResponse
    {
        $serverKey = config('midtrans.server_key');
        if (! $serverKey) {
            Log::warning('[Midtrans] Server key not configured, skipping webhook verification');

            return response()->json(['status' => 'skipped']);
        }

        $authHeader = $request->header('Authorization', '');
        $expectedAuth = 'Basic '.base64_encode($serverKey.':');
        if ($authHeader !== $expectedAuth) {
            Log::warning('[Midtrans] Invalid webhook signature', ['ip' => $request->ip()]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        Log::info('[Midtrans] Webhook diterima', $request->all());

        $handled = $midtrans->handleNotification($request->all());

        if (! $handled) {
            Log::warning('[Midtrans] Webhook tidak diproses (order_id tidak dikenali).', $request->all());
        }

        // Midtrans mengharapkan 200 agar tidak mengirim ulang.
        return response()->json(['status' => 'success']);
    }
}