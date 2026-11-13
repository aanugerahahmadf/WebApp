<?php

namespace App\Services\CourierTracking;

use App\Models\Order\Order;
use App\Models\Order\OrderTimeline;
use Illuminate\Support\Carbon;
use Throwable;

class OrderShippingSyncService
{
    public function __construct(private readonly CourierTrackingService $tracking) {}

    /**
     * @return array{synced: bool, message: string, status: ?string, events_count: int}
     */
    public function sync(Order $order, bool $force = false): array
    {
        $courier = $order->shipping_courier;
        $resi = $order->tracking_number;
        if (blank($courier) || blank($resi)) {
            return ['synced' => false, 'message' => 'Kurir atau nomor resi belum diisi.', 'status' => $order->shipping_status, 'events_count' => 0];
        }

        $result = $this->tracking->track($courier, $resi);
        if (! $result->success) {
            return ['synced' => false, 'message' => $result->error ?? 'Gagal sinkron pelacakan.', 'status' => $order->shipping_status, 'events_count' => 0];
        }

        $updates = [];
        if ($result->status && $result->status !== $order->shipping_status) {
            $updates['shipping_status'] = $result->status;
        }
        if (in_array($result->status, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned'], true) && ! $order->shipped_at) {
            $updates['shipped_at'] = now();
        }
        if ($result->status === 'delivered' && ! $order->delivered_at) {
            $updates['delivered_at'] = now();
        }
        if (! filled($order->tracking_url)) {
            $url = \App\Support\ShippingTracking\ShippingTracking::trackingUrl($courier, $resi);
            if ($url) $updates['tracking_url'] = $url;
        }
        if ($updates !== []) {
            $order->fill($updates)->save();
        }

        $eventsCount = 0;
        foreach ($result->events as $event) {
            $desc = trim((string) ($event['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $time = isset($event['time']) ? trim((string) $event['time']) : null;
            $location = isset($event['location']) ? trim((string) $event['location']) : null;
            $meta = [
                'provider' => config('services.courier_tracking.driver', 'binderbyte'),
                'courier' => $courier,
                'tracking_number' => $resi,
                'time' => $time !== '' ? $time : null,
                'location' => $location !== '' ? $location : null,
                'checkpoint_status' => $event['status'] ?? null,
            ];

            $query = OrderTimeline::query()
                ->where('order_id', $order->id)
                ->where('status', 'shipping')
                ->where('description', $desc);
            if (filled($meta['time'])) {
                $query->where('metadata->time', $meta['time']);
            }
            $exists = $query->exists();
            if ($exists) {
                continue;
            }

            try {
                OrderTimeline::create([
                    'order_id' => $order->id,
                    'status' => 'shipping',
                    'description' => $desc,
                    'metadata' => $meta,
                    'created_by' => null,
                ]);
                $eventsCount++;
            } catch (Throwable) {
                // unique/constraint issues ignored; best effort
            }
        }

        // Jika provider memberi status/ringkasan tanpa checkpoint baru, simpan
        // satu ringkasan agar status sinkron tetap tercatat (idempotent).
        if ($eventsCount === 0 && ($result->summary || $result->status)) {
            $summaryDesc = trim((string) ($result->summary ?: ('Status: ' . $result->status)));
            if ($summaryDesc !== '') {
                $already = OrderTimeline::query()
                    ->where('order_id', $order->id)
                    ->where('status', 'shipping')
                    ->where('description', $summaryDesc)
                    ->exists();
                if (! $already) {
                    try {
                        OrderTimeline::create([
                            'order_id' => $order->id,
                            'status' => 'shipping',
                            'description' => $summaryDesc,
                            'metadata' => [
                                'provider' => config('services.courier_tracking.driver', 'binderbyte'),
                                'courier' => $courier,
                                'tracking_number' => $resi,
                                'time' => null,
                                'location' => null,
                                'checkpoint_status' => $result->status,
                                'summary' => true,
                            ],
                            'created_by' => null,
                        ]);
                        $eventsCount++;
                    } catch (Throwable) {
                    }
                }
            }
        }

        $msg = $eventsCount > 0
            ? "Sinkronisasi berhasil: {$eventsCount} pembaruan pelacakan."
            : ($result->summary ? "Status pelacakan: {$result->summary}" : 'Pelacakan tersinkron (tidak ada checkpoint baru).');

        return ['synced' => true, 'message' => $msg, 'status' => $order->shipping_status ?? $result->status, 'events_count' => $eventsCount];
    }
}