<?php

namespace App\Observers\MessageObserver;

use App\Models\Message\Message;
use App\Models\User\User;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MessageObserver
{
    public function created(Message $message): void
    {
        if (isset($message->meta['is_bot']) && $message->meta['is_bot']) {
            return;
        }

        if (isset($message->meta['is_payment_update']) && $message->meta['is_payment_update']) {
            return;
        }

        $inbox = $message->inbox;
        if (! $inbox) {
            return;
        }

        $sender = $message->sender;
        if (! $sender) {
            return;
        }

        $lockKey = "msg_notif_{$inbox->id}_{$message->id}";
        if (Cache::has($lockKey)) {
            return;
        }
        Cache::put($lockKey, true, now()->addSeconds(10));

        $recipientIds = collect($inbox->user_ids)
            ->reject(fn ($id) => $id == $sender->id)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::whereIn('id', $recipientIds)->get();
        $senderName = $sender->full_name ?? $sender->name ?? 'Seseorang';
        $preview = $this->buildPreview($message);

        foreach ($recipients as $recipient) {
            $this->sendBellNotification($recipient, $senderName, $preview, $inbox->id);
            $this->sendWhatsApp($recipient, $senderName, $preview);
            $this->sendEmail($recipient, $sender, $senderName, $preview, $message);
        }
    }

    private function sendBellNotification(User $recipient, string $senderName, string $preview, int $inboxId): void
    {
        try {
            [$title] = PlatformNotificationService::withRecipientLocale(
                $recipient,
                fn () => [__('Pesan baru dari :name', ['name' => $senderName])]
            );

            Notification::make()
                ->title($title)
                ->body($preview)
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('info')
                ->info()
                ->sendToDatabase($recipient);

            PlatformNotificationService::send($recipient, $title, $preview);
        } catch (\Throwable $e) {
            Log::warning('[MessageObserver] Bell notification failed: '.$e->getMessage());
        }
    }

    private function sendWhatsApp(User $recipient, string $senderName, string $preview): void
    {
        try {
            $phone = $this->normalizePhone($recipient->whatsapp ?? $recipient->phone ?? '');

            if (empty($phone)) {
                Log::info('[MessageObserver] WhatsApp skipped — no phone for user #'.$recipient->id);

                return;
            }

            $token = config('services.fonnte_token', env('FONNTE_TOKEN', ''));
            if (empty($token)) {
                Log::warning('[MessageObserver] WhatsApp skipped — FONNTE_TOKEN not set');

                return;
            }

            $waMessage = PlatformNotificationService::withRecipientLocale(
                $recipient,
                fn () => __('whatsapp.new_message_from', ['name' => $senderName])
                    . $preview
                    . __('whatsapp.new_message_reply')
            );

            $response = Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $waMessage,
                ]);

            if ($response->successful()) {
                Log::info("[MessageObserver] WhatsApp sent to {$phone}");
            } else {
                Log::warning("[MessageObserver] WhatsApp failed ({$response->status()}): ".$response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('[MessageObserver] WhatsApp exception: '.$e->getMessage());
        }
    }

    private function sendEmail(User $recipient, User $sender, string $senderName, string $preview, Message $message): void
    {
        try {
            if (empty($recipient->email)) {
                return;
            }

            $appName = config('app.name', 'Aplikasi');
            $html = $this->buildEmailHtml($senderName, $preview, $message, $appName);

            Mail::send([], [], function ($mail) use ($recipient, $senderName, $appName, $html) {
                $mail->to($recipient->email)
                    ->subject("Pesan baru dari {$senderName} - {$appName}")
                    ->html($html);
            });

            Log::info("[MessageObserver] Email sent to {$recipient->email} from {$senderName}");
        } catch (\Throwable $e) {
            Log::warning('[MessageObserver] Email failed: '.$e->getMessage());
        }
    }

    private function buildPreview(Message $message): string
    {
        if (! empty($message->message)) {
            return mb_substr($message->message, 0, 200);
        }

        if (isset($message->meta['name'])) {
            return 'Mengirim informasi tentang: '.$message->meta['name'];
        }

        return 'Mengirim lampiran atau gambar.';
    }

    private function buildEmailHtml(string $senderName, string $preview, Message $message, string $appName): string
    {
        $time = $message->created_at?->setTimezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') ?? date('d M Y, H:i');
        $escaped = nl2br(htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'));
        $meta = $message->meta ?? [];

        // ── Order card detail (jika pesan punya meta order) ──────────────────
        $orderHtml = '';
        if (! empty($meta['order_number']) || ! empty($meta['is_order']) || ! empty($meta['is_cancellation'])) {
            $orderNumber = $meta['order_number'] ?? '-';
            $itemName = htmlspecialchars($meta['name'] ?? '-', ENT_QUOTES, 'UTF-8');
            $price = isset($meta['price']) ? 'Rp '.number_format((float) $meta['price'], 0, ',', '.') : '-';
            $payStatus = htmlspecialchars($meta['payment_status'] ?? '-', ENT_QUOTES, 'UTF-8');
            $isCancelled = ! empty($meta['is_cancellation']);
            $isRefunded = ! empty($meta['is_refunded']);
            $imgSrc = ! empty($meta['image']) && filter_var($meta['image'], FILTER_VALIDATE_URL) ? $meta['image'] : null;

            $statusColor = $isCancelled ? '#dc2626' : '#d97706';
            $statusText = $isCancelled ? '❌ Pesanan Dibatalkan' : ('Status: '.$payStatus);

            $orderHtml = '
            <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin:16px 0;">
                '.($imgSrc ? '<img src="'.$imgSrc.'" alt="'.$itemName.'" style="width:100%;height:160px;object-fit:cover;display:block;">' : '').'
                <div style="padding:12px 16px;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:bold;color:'.$statusColor.';text-transform:uppercase;">'.$statusText.'</p>
                    <p style="margin:0 0 4px;font-size:15px;font-weight:bold;color:#111827;">'.$itemName.'</p>
                    <p style="margin:0 0 8px;font-size:14px;font-weight:bold;color:#d97706;">'.$price.'</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <tr>
                            <td style="color:#6b7280;padding:3px 0;width:45%;">No. Pesanan</td>
                            <td style="color:#111827;font-weight:bold;">#'.htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8').'</td>
                        </tr>
                        '.($isCancelled && $isRefunded ? '
                        <tr>
                            <td style="color:#6b7280;padding:3px 0;">Refund</td>
                            <td style="color:#16a34a;font-weight:bold;">Dana dikembalikan ke saldo</td>
                        </tr>' : '').'
                    </table>
                </div>
            </div>';
        }

        // ── Logo ──────────────────────────────────────────────────────────────
        $logoPath = public_path('images/logo.png');
        $logoHtml = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
            $logoHtml = '<img src="'.$logoBase64.'" alt="'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'" style="height:40px;width:auto;">';
        } else {
            $logoHtml = '<span style="font-size:18px;font-weight:bold;color:#fff;">'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'</span>';
        }

        return '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
            .'<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">'
            .'<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);border:1px solid #e5e7eb;">'

            // Header
            .'<div style="background:#4f46e5;padding:20px 24px;text-align:center;">'
            .$logoHtml
            .'<p style="color:#c7d2fe;margin:8px 0 0;font-size:13px;">'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'</p>'
            .'</div>'

            // Body
            .'<div style="padding:24px;">'
            .'<p style="margin:0 0 4px;font-size:13px;color:#6b7280;">Dari</p>'
            .'<p style="margin:0 0 16px;font-size:16px;font-weight:bold;color:#111827;">'.htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8').'</p>'

            // Message bubble
            .'<div style="background:#f9fafb;border-left:4px solid #4f46e5;border-radius:4px;padding:12px 16px;margin-bottom:4px;">'
            .'<p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">'.$escaped.'</p>'
            .'</div>'
            .'<p style="margin:4px 0 0;font-size:11px;color:#9ca3af;">'.$time.'</p>'

            // Order card (jika ada)
            .$orderHtml

            .'</div>'

            // Footer
            .'<div style="background:#f9fafb;padding:16px 24px;text-align:center;border-top:1px solid #e5e7eb;">'
            .'<p style="margin:0;font-size:12px;color:#9ca3af;">Buka aplikasi untuk membalas pesan ini.</p>'
            .'</div>'

            .'</div></body></html>';
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (empty($phone)) {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }
}
