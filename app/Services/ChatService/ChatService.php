<?php

namespace App\Services\ChatService;


use App\Filament\Welcome\Resources\CatalogResource\CatalogResource;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\User\User;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Support\Facades\Auth;

class ChatService
{
    /**
     * Get or create an inbox between a user and the first super admin.
     * Returns null if no admin exists (graceful degradation).
     *
     * "Admin" di sini berarti BARIS `users` yang tertaut ke akun super admin di
     * tabel `admins` -- bukan objek `Admin` itu sendiri, karena
     * `inboxes.user_ids` menyimpan `users.id`. Pencarian lewat `ChatStaff`,
     * bukan `whereHas('roles')` langsung di sini, supaya tidak ada dua versi
     * "cari admin" yang bisa kembar berbeda.
     *
     * Kalau tabel `admins` punya admin aktif tapi `user_id`-nya masih null --
     * keadaan default, sebelum `php artisan admin:grant-storefront` dijalankan
     * -- ini mengembalikan null dan pemanggil degrade dengan rapi: pesan tetap
     * tersimpan, hanya tanpa staff tujuan.
     */
    public static function getOrCreateInboxWithAdmin(int $userId): ?Inbox
    {
        $admin = ChatStaff::adminUser();

        if (! $admin) {
            // Log warning but don't throw - allows chat to work when admin is configured later
            logger()->warning('Super Admin belum punya identitas belanja tertaut. Jalankan `php artisan admin:grant-storefront`. Chat admin feature unavailable.');
            return null;
        }

        $inbox = Inbox::query()
            ->whereJsonContains('user_ids', $userId)
            ->whereJsonContains('user_ids', $admin->id)
            ->first();

        if (! $inbox) {
            $inbox = Inbox::create([
                'user_ids' => [$userId, $admin->id],
            ]);
        }

        return $inbox;
    }

    /**
     * Send a context message (catalog/package card) to an inbox.
     * Accepts optional sender user_id (for guest users).
     */
    public static function sendContextMessage(Inbox $inbox, array $meta, ?int $senderUserId = null): Message
    {
        // Avoid sending duplicate context cards for the same item in a short time
        // Only skip if the last message is also a context card (not an order card) for the same item
        $lastMessage = $inbox->messages()->latest('id')->first();
        if (
            $lastMessage
            && isset($lastMessage->meta['id'])
            && $lastMessage->meta['id'] == $meta['id']
            && empty($lastMessage->meta['is_order'])
        ) {
            return $lastMessage;
        }

        $message = Message::create([
            'inbox_id' => $inbox->id,
            'user_id' => $senderUserId ?? Auth::id(),
            'message' => __('Saya menanyakan tentang :itemType ini: :name', [
                'itemType' => __($meta['type'] == 'catalog' ? 'Katalog' : 'Paket'),
                'name' => $meta['name'] ?? '',
            ]),
            'meta' => $meta,
        ]);

        // Dispatch bot reply if user is not admin
        $sender = $senderUserId ? User::find($senderUserId) : Auth::user();
        if ($sender && ! ChatStaff::isStaff($sender)) {
            SendBotReply::dispatch($message->id);
        }

        return $message;
    }

    /**
     * Send a report message mirroring the mobile app's `openReportChat` flow.
     *
     * [category] determines the CS category stored on the inbox (order/payment
     * map to `order_help`; everything else maps to `bug_report`). The message
     * text replicates the mobile report template:
     * `Saya ingin melaporkan ini: Jenis: {category} Item: {item}` + optional
     * [details] + the standard report closing prompt.
     */
    public static function sendReportMessage(Inbox $inbox, string $category, string $itemName, ?string $details = null, array $meta = [], ?int $senderUserId = null): Message
    {
        $csCategory = match ($category) {
            'order', 'payment' => 'order_help',
            default => 'bug_report',
        };

        // Store category on inbox meta if not already set (mirrors both the
        // mobile app and ChatController@updateMessage behaviour).
        $inboxMeta = $inbox->meta ?? [];
        if (empty($inboxMeta['cs_category'])) {
            $inboxMeta['cs_category'] = $csCategory;
            $inbox->meta = $inboxMeta;
            $inbox->save();
        }

        $catLabel = match ($category) {
            'catalog' => __('Katalog'),
            'package' => __('Paket'),
            
            'order' => __('Pesanan'),
            'review' => __('Ulasan'),
            default => __('Umum'),
        };
        $subject = trim($itemName) !== '' ? trim($itemName) : $catLabel;

        $messageText = __('Saya ingin melaporkan ini:')
            ."\n".__('Jenis: :category', ['category' => $catLabel])
            ."\n".__('Item: :item', ['item' => $subject]);

        if ($details !== null && trim($details) !== '') {
            $messageText .= "\n\n".$details;
        }
        $messageText .= "\n\n".__('Mohon bantu tindak lanjuti laporan ini, terima kasih.');

        $senderId = $senderUserId ?? Auth::id();

        $message = Message::create([
            'inbox_id' => $inbox->id,
            'user_id' => $senderId,
            'message' => $messageText,
            'meta' => $meta ?: null,
        ]);

        // Dispatch bot reply if sender is not admin. Null-safe because a
        // guest row always counts as "not admin".
        $sender = $senderId ? User::find($senderId) : Auth::user();
        if ($sender && ! ChatStaff::isStaff($sender)) {
            SendBotReply::dispatch($message->id);
        }

        return $message;
    }

    /**
     * Send an order confirmation message (order card) to an inbox.
     */
    public static function sendOrderMessage(Inbox $inbox, Order $order): Message
    {
        $admin = ChatStaff::adminUser();

        $type = $order->package_id ? 'package' : 'catalog';
        $item = $order->package ?? $order->catalog;

        $message = Message::create([
            'inbox_id' => $inbox->id,
            'user_id' => $admin ? $admin->id : $order->user_id, // Kirim atas nama Admin
            'message' => __('Halo Kak :userName, pesanan baru Anda telah kami terima dengan nomor: :orderNumber. Silakan lakukan pembayaran agar pesanan segera diproses.', [
                'userName' => $order->user->name,
                'orderNumber' => $order->order_number,
            ]),
            'meta' => [
                'type' => $type,
                'id' => $item->id,
                'name' => $item->name,
                'price' => $order->total_price,
                'image' => $item->image_url,
                'url' => $order->package_id ? self::resourceUrl(CatalogResource::class) : self::resourceUrl(CatalogResource::class),
                'is_order' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status->getLabel(),
            ],
        ]);

        return $message;
    }

    /**
     * Resolve a Filament resource URL even when no panel is current (e.g. API context).
     */
    private static function resourceUrl(string $resource): ?string
    {
        try {
            return $resource::getUrl(panel: 'welcome');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
