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

        // Tanpa server key TIDAK ADA nilai yang bisa dicocokkan dengan header
        // Authorization, jadi request apa pun tidak mungkin terverifikasi.
        // Versi lama cabang ini mengembalikan 200 {"status":"skipped"} dengan
        // alasan "skipping webhook verification" -- yang justru membuat
        // endpoint terbuka tepat saat konfigurasi paling tidak lengkap: di
        // environment tanpa .env (runner CI, container yang lupa menyalin
        // env) setiap payload diterima tanpa satu pun pemeriksaan.
        //
        // Endpoint ini menulis status transaksi, jadi menerima payload tak
        // terverifikasi berarti siapa pun bisa menandai pesanan sebagai lunas.
        // Karena itu kondisi ini menolak, sama seperti signature yang salah,
        // dan statusnya sama: 403.
        if (! $serverKey) {
            Log::error('[Midtrans] Server key not configured, rejecting webhook');

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
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