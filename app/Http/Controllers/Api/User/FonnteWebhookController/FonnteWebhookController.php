<?php

namespace App\Http\Controllers\Api\User\FonnteWebhookController;

use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Http\Controllers\Controller;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FonnteWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp messages from Fonnte webhook.
     *
     * Fonnte sends POST request with:
     * - device: sender's WhatsApp number (628xxx)
     * - message: text content
     * - media: media URL if any
     * - pushname: sender's WhatsApp name
     *
     * Docs: https://fonnte.com/api-documentation
     */
    public function handleIncomingMessage(Request $request)
    {
        try {
            Log::info('[Fonnte Webhook] Incoming message', $request->all());

            // Validate Fonnte token untuk keamanan
            $token = $request->header('Authorization') ?? $request->input('token');
            $expectedToken = config('services.fonnte_token');

            if (!hash_equals((string)$expectedToken, (string)($token ?? ''))) {
                Log::warning('[Fonnte Webhook] Invalid token', ['received' => $token]);

                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            // Extract data dari Fonnte
            $senderPhone = $request->input('device'); // 628xxx
            $messageText = $request->input('message');
            $mediaUrl = $request->input('media');
            $pushname = $request->input('pushname', 'WhatsApp User');

            if (empty($senderPhone) || empty($messageText)) {
                Log::warning('[Fonnte Webhook] Missing required fields', $request->all());

                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }

            // Normalisasi nomor: 628xxx → 08xxx untuk match dengan DB
            $normalizedPhone = $this->normalizePhoneForDb($senderPhone);

            // Cari user berdasarkan nomor WhatsApp atau phone
            $user = User::where('whatsapp', $normalizedPhone)
                ->orWhere('phone', $normalizedPhone)
                ->first();

            if (! $user) {
                Log::info('[Fonnte Webhook] User not found for phone: '.$normalizedPhone);

                // Opsi 1: Buat user baru otomatis (guest)
                // $user = $this->createGuestUser($normalizedPhone, $pushname);

                // Opsi 2: Skip dan log saja (pilihan saat ini)
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'User not registered',
                    'phone' => $normalizedPhone,
                ], 200);
            }

            // Get or create inbox dengan admin
            $inbox = ChatService::getOrCreateInboxWithAdmin($user->id);

            // Simpan pesan ke database
            $message = Message::create([
                'inbox_id' => $inbox->id,
                'user_id' => $user->id,
                'message' => $messageText,
                'read_by' => [$user->id], // User sudah baca (dia yang kirim)
                'read_at' => [now()],
                'notified' => [$user->id],
                'meta' => [
                    'source' => 'whatsapp',
                    'pushname' => $pushname,
                    'phone' => $senderPhone,
                ],
            ]);

            // Jika ada media, download dan attach
            if (! empty($mediaUrl) && filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                try {
                    $message->addMediaFromUrl($mediaUrl)
                        ->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
                } catch (\Throwable $e) {
                    Log::warning('[Fonnte Webhook] Failed to download media: '.$e->getMessage());
                }
            }

            Log::info('[Fonnte Webhook] Message saved', [
                'message_id' => $message->id,
                'inbox_id' => $inbox->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Message received',
                'data' => [
                    'message_id' => $message->id,
                    'inbox_id' => $inbox->id,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('[Fonnte Webhook] Exception: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle WhatsApp connection status updates from Fonnte.
     */
    public function handleConnectionStatus(Request $request)
    {
        $token = $request->header('Authorization') ?? $request->input('token');
        $expectedToken = config('services.fonnte_token');

        if ($token !== $expectedToken) {
            Log::warning('[Fonnte Webhook] Connection status: invalid token');

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        Log::info('[Fonnte Webhook] Connection status', $request->all());

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Handle message delivery status updates from Fonnte.
     */
    public function handleMessageStatus(Request $request)
    {
        $token = $request->header('Authorization') ?? $request->input('token');
        $expectedToken = config('services.fonnte_token');

        if ($token !== $expectedToken) {
            Log::warning('[Fonnte Webhook] Message status: invalid token');

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        Log::info('[Fonnte Webhook] Message status', $request->all());

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Normalisasi nomor dari Fonnte (628xxx) ke format DB (08xxx).
     */
    private function normalizePhoneForDb(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone); // hapus non-digit

        // 628xxx → 08xxx
        if (str_starts_with($phone, '62')) {
            $phone = '0'.substr($phone, 2);
        }

        return $phone;
    }

    /**
     * Buat user guest otomatis untuk nomor yang belum terdaftar.
     * (Opsional - uncomment jika mau auto-create user)
     */
    private function createGuestUser(string $phone, string $name): User
    {
        return User::create([
            'full_name' => $name,
            'username' => 'wa_'.substr($phone, -8),
            'email' => 'wa_'.substr($phone, -8).'@guest.local',
            'password' => str()->random(32),
            'phone' => $phone,
            'whatsapp' => $phone,
        ]);
    }
}
