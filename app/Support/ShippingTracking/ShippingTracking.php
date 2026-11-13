<?php

namespace App\Support\ShippingTracking;

class ShippingTracking
{
    /**
     * @return array<string, string>
     */
    public static function couriers(): array
    {
        return [
            'jnt' => 'J&T Express',
            'sicepat' => 'SiCepat',
            'shopee_express' => 'Shopee Express',
            'jne' => 'JNE',
            'anteraja' => 'AnterAja',
            'ninja' => 'Ninja Xpress',
            'id_express' => 'ID Express',
            'pos' => 'POS Indonesia',
            'tiki' => 'TIKI',
            'lion' => 'Lion Parcel',
            'sap' => 'SAP Express',
            'wahana' => 'Wahana',
            'jnt_cargo' => 'J&T Cargo',
            'go_send' => 'GoSend',
            'grab_express' => 'GrabExpress',
            'other' => __('Kurir Lainnya'),
        ];
    }

    public static function courierName(?string $code, ?string $customName = null): ?string
    {
        if ($code === null || $code === '') {
            return $customName;
        }
        if ($code === 'other' && filled($customName)) {
            return $customName;
        }

        return self::couriers()[$code] ?? $customName ?? $code;
    }

    public static function trackingUrl(?string $courier, ?string $resi): ?string
    {
        $resi = trim((string) $resi);
        if ($resi === '') {
            return null;
        }
        $encoded = rawurlencode($resi);

        return match ($courier) {
            'jnt' => "https://jet.co.id/track?bill={$encoded}",
            'jnt_cargo' => "https://jet.co.id/track?bill={$encoded}",
            'sicepat' => "https://www.sicepat.com/checkAwb?waybill={$encoded}",
            'shopee_express' => "https://spx.co.id/track?{$encoded}",
            'jne' => "https://www.jne.co.id/id/tracking/trace",
            'anteraja' => "https://anteraja.id/id/tracking?awb={$encoded}",
            'ninja' => "https://www.ninjaxpress.co/id-id/tracking?waybill={$encoded}",
            'id_express' => "https://idexpress.com/id/tracking/?waybill_no={$encoded}",
            'pos' => "https://www.posindonesia.co.id/id/tracking",
            'tiki' => "https://tiki.id/id/tracking",
            'lion' => "https://lionparcel.com/id/track/stt?q={$encoded}",
            'sap' => "https://www.sap-express.id/id/tracking?awb={$encoded}",
            'wahana' => "https://www.wahana.com/lacak-paket?awb={$encoded}",
            'go_send' => null,
            'grab_express' => null,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function shippingStatuses(): array
    {
        return [
            'pending' => __('Menunggu Pengiriman'),
            'packed' => __('Dikemas'),
            'picked_up' => __('Diambil Kurir'),
            'in_transit' => __('Dalam Perjalanan'),
            'out_for_delivery' => __('Sedang Diantar'),
            'delivered' => __('Terkirim'),
            'returned' => __('Dikembalikan'),
            'failed' => __('Gagal Kirim'),
        ];
    }
}