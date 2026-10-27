<?php

namespace App\Jobs\SendBotReply;

use App\Models\Message\Message;
use App\Services\AiConversationService\AiConversationService;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Generate one contextual reply for a customer/guest message. */
class SendBotReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 1;

    public function __construct(public int $messageId, ?string $locale = null)
    {
        // Kept for backwards-compatible dispatch calls. The answer language
        // is detected from the actual message, never from the UI locale.
    }

    public function handle(AiConversationService $ai): void
    {
        $userMessage = Message::with('inbox')->find($this->messageId);
        if (! $userMessage || ! $userMessage->inbox) {
            return;
        }

        $meta = $userMessage->meta ?? [];
        if (! empty($meta['is_bot'])) {
            return;
        }

        // Queue retries and duplicate browser/API submissions must not create
        // multiple answers for the same source message.
        $lock = cache()->lock('ai-chat-reply:'.$userMessage->id, 30);
        if (! $lock->get()) {
            return;
        }

        try {
            $admin = ChatStaff::adminUser();
            if (! $admin) {
                logger()->warning('AI chat reply skipped: no storefront staff identity is configured.');
                return;
            }

            // Re-check inside the lock in case another worker completed first.
            if ($userMessage->inbox->messages()->where('meta->is_bot', true)->where('id', '>', $userMessage->id)->exists()) {
                return;
            }

            $language = $ai->detectLanguage((string) $userMessage->message);
            $reply = $ai->reply($userMessage);

            Message::create([
                'inbox_id' => $userMessage->inbox_id,
                'user_id' => $admin->id,
                'message' => $reply,
                'meta' => [
                    'is_bot' => true,
                    'ai' => true,
                    'language' => $language,
                    'reply_to_id' => $userMessage->id,
                ],
            ]);
        } finally {
            $lock->release();
        }
    }
}
