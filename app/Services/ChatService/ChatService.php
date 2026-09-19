<?php

namespace App\Services\ChatService;

use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;

class ChatService
{
    /**
     * Get or create an inbox between a user and the first super admin.
     */
    public static function getOrCreateInboxWithAdmin(int $userId): Inbox
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        if (! $admin) {
            throw new \Exception('Super Admin not found.');
        }

        $inbox = Inbox::query()
            ->whereJsonContains('user_ids', $userId, 'and', false)
            ->whereJsonContains('user_ids', $admin->id, 'and', false)
            ->first();

        if (! $inbox) {
            $inbox = Inbox::create([
                'user_ids' => [$userId, $admin->id],
            ]);
        }

        return $inbox;
    }

    /**
     * Send a context message (product/package card) to an inbox.
     */
    public static function sendContextMessage(Inbox $inbox, array $meta): Message
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
            'user_id' => Auth::id(),
            'message' => __('Saya menanyakan tentang :itemType ini: :name', [
                'itemType' => __($meta['type'] == 'product' ? 'Produk' : 'Paket'),
                'name' => $meta['name'] ?? '',
            ]),
            'meta' => $meta,
        ]);

        // Dispatch bot reply if user is not admin
        if (Auth::user() && ! Auth::user()->hasRole('super_admin')) {
            SendBotReply::dispatch($message->id)->delay(now()->addSeconds(5));
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
    public static function sendReportMessage(Inbox $inbox, string $category, string $itemName, ?string $details = null, array $meta = []): Message
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
            'product' => __('Produk'),
            'package' => __('Paket'),
            'vendor' => __('Vendor'),
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

        $message = Message::create([
            'inbox_id' => $inbox->id,
            'user_id' => Auth::id(),
            'message' => $messageText,
            'meta' => $meta ?: null,
        ]);

        // Dispatch bot reply if user is not admin
        if (Auth::user() && ! Auth::user()->hasRole('super_admin')) {
            SendBotReply::dispatch($message->id)->delay(now()->addSeconds(5));
        }

        return $message;
    }

    /**
     * Send an order confirmation message (order card) to an inbox.
     */
    public static function sendOrderMessage(Inbox $inbox, Order $order): Message
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        $type = $order->package_id ? 'package' : 'product';
        $item = $order->package ?? $order->product;

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
                'url' => $order->package_id ? self::resourceUrl(PackageResource::class) : self::resourceUrl(ProductResource::class),
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
            return $resource::getUrl(panel: 'user');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
