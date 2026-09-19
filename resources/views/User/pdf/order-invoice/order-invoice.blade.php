<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 12px;
    color: #333;
    background: #fff;
}
.page { padding: 40px 48px; }

/* ── Header ── */
.header-table { width: 100%; margin-bottom: 28px; }
.header-table td { vertical-align: top; }
.company-name { font-size: 16px; font-weight: 600; color: #111; }
.company-sub  { font-size: 10px; color: #777; margin-top: 3px; }
.invoice-label { font-size: 24px; font-weight: 800; color: #111; text-align: right; }
.invoice-meta  { font-size: 10px; color: #666; text-align: right; margin-top: 4px; line-height: 1.7; }

/* ── Divider ── */
hr { border: none; border-top: 1.5px solid #ccc; margin: 0 0 20px; }
hr.light { border-top: 1px solid #e0e0e0; margin: 16px 0; }

/* ── Two-column info ── */
.info-table { width: 100%; margin-bottom: 20px; }
.info-table td { vertical-align: top; width: 50%; }
.info-table td + td { padding-left: 24px; }
.info-section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #999; margin-bottom: 5px; }
.info-row { margin-bottom: 3px; line-height: 1.6; }
.info-key { color: #777; }
.info-val { font-weight: 600; color: #111; }

/* ── Status badge ── */
.badge { display: inline-block; padding: 2px 8px; border-radius: 2px; font-size: 10px; font-weight: 600; }
.badge-paid    { background: #e8f5e9; color: #2e7d32; }
.badge-partial { background: #e3f2fd; color: #1565c0; }
.badge-pending { background: #fff8e1; color: #e65100; }
.badge-failed  { background: #ffebee; color: #b71c1c; }

/* ── Items table ── */
.section-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #999; margin-bottom: 6px; }
table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
table.items thead tr { background: #f5f5f5; }
table.items th { padding: 7px 8px; text-align: left; font-size: 10px; color: #555; border-bottom: 1px solid #ddd; }
table.items th.right { text-align: right; }
table.items td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
table.items td.right { text-align: right; }
table.items .item-name { font-weight: 600; color: #111; }
table.items .item-sub  { font-size: 10px; color: #888; margin-top: 2px; }

/* ── Totals ── */
table.totals { width: 260px; margin-left: auto; border-collapse: collapse; margin-bottom: 20px; }
table.totals td { padding: 5px 8px; font-size: 12px; }
table.totals td.label { color: #555; }
table.totals td.value { text-align: right; font-weight: 600; }
table.totals tr.grand td { border-top: 1.5px solid #222; font-size: 14px; padding-top: 8px; }

/* ── Payment info ── */
.payment-box { background: #f9f9f9; border: 1px solid #e8e8e8; border-radius: 3px; padding: 10px 12px; margin-bottom: 20px; }
.payment-box table { width: 100%; border-collapse: collapse; }
.payment-box td { padding: 3px 0; font-size: 12px; }
.payment-box td.key { color: #777; width: 45%; }
.payment-box td.val { font-weight: 600; color: #111; }

/* ── Notes ── */
.notes-box { background: #fafafa; border-left: 3px solid #ddd; padding: 8px 12px; font-size: 11px; color: #555; margin-bottom: 20px; line-height: 1.6; }

/* ── Footer ── */
.footer { border-top: 1px solid #e0e0e0; padding-top: 10px; font-size: 9px; color: #aaa; text-align: center; line-height: 1.7; }
</style>
</head>
<body>
<div class="page">

@php
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;

// Payment status
$ps = $order->payment_status instanceof OrderPaymentStatus
    ? $order->payment_status
    : OrderPaymentStatus::tryFrom((string) $order->payment_status);
$psVal   = $ps?->value ?? '';
$psLabel = $ps?->getLabel() ?? $psVal;
$isPaid  = in_array($psVal, ['paid', 'partial']);
$badgeClass = match($psVal) {
    'paid'    => 'badge-paid',
    'partial' => 'badge-partial',
    'failed'  => 'badge-failed',
    default   => 'badge-pending',
};

// Order status
$os = $order->status instanceof OrderStatus
    ? $order->status
    : OrderStatus::tryFrom((string) $order->status);
$osLabel = $os?->getLabel() ?? (string) $order->status;

// Item
$item     = $order->package ?? $order->product;
$itemType = $order->package_id
    ? \App\Filament\User\Resources\PackageResource\PackageResource::getModelLabel()
    : \App\Filament\User\Resources\ProductResource\ProductResource::getModelLabel();
$itemName = $item?->name ?? '-';
$itemCat  = $item?->category?->name ?? '-';
$weddingFlowersDecorasi = '-';

// Transaction
$tx = $order->latestTransaction;
$paymentGateway = $tx?->payment_gateway ?? '-';
$paymentMethod  = $tx?->payment_method  ?? '-';
$paidAt         = $tx?->paid_at ? \Carbon\Carbon::parse($tx->paid_at)->format('d M Y, H:i') : '-';
$txRef          = $tx?->reference_number ?? '-';
$txStatus       = $tx?->status instanceof \App\Enums\PaymentStatus\PaymentStatus
    ? $tx->status->getLabel()
    : ($tx?->status ?? '-');
$adminFee = (float) ($tx?->admin_fee ?? 0);

// Email superadmin (bukan email pengirim SMTP)
$adminEmail = \App\Models\User\User::whereHas('roles', fn($q) => $q->where('name','super_admin'))
    ->value('email') ?? config('mail.from.address');

// Logo — embed sebagai base64 agar tampil di PDF
$logoBase64 = null;
$logoPath   = public_path('images/logo.png');
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

// Gambar item — resize 70x70 dengan GD, deteksi MIME dari konten file
$imgSrc = null;
if ($item) {
    $col   = $order->package_id ? 'package_image' : 'product_image';
    $media = $item->getFirstMedia($col);
    if ($media && file_exists($media->getPath())) {
        try {
            $path  = $media->getPath();
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);

            [$w, $h] = getimagesize($path);
            $size = 70; $r = min($size/$w, $size/$h, 1.0);
            $nw = (int)($w*$r); $nh = (int)($h*$r);

            $src = match($mime) {
                'image/jpeg' => imagecreatefromjpeg($path),
                'image/png'  => imagecreatefrompng($path),
                'image/gif'  => imagecreatefromgif($path),
                'image/webp' => imagecreatefromwebp($path),
                default      => null,
            };
            if ($src) {
                $dst   = imagecreatetruecolor($nw, $nh);
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefill($dst, 0, 0, $white);
                imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);
                ob_start(); imagejpeg($dst, null, 80);
                $imgSrc = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
                imagedestroy($src); imagedestroy($dst);
            }
        } catch (\Throwable) {}
    }
}

$appName = config('app.name', 'Wedding Flowers Decorasi');
$now     = now()->format('d M Y, H:i');
@endphp

{{-- ── HEADER ── --}}
<table class="header-table">
  <tr>
    <td>
      @if($logoBase64)
        <img src="{{ $logoBase64 }}" style="height:48px;width:auto;display:block;margin-bottom:6px;">
      @endif
      <div class="company-name">{{ $appName }}</div>
      <div class="company-sub">{{ $adminEmail }}</div>
    </td>
    <td>
      <div class="invoice-label">{{ __('INVOICE') }}</div>
      <div class="invoice-meta">
        {{ __('No.') }} &nbsp;<strong>#{{ $order->order_number }}</strong><br>
        {{ __('Tanggal:') }} {{ $now }}<br>
        <span class="badge {{ $badgeClass }}">{{ $psLabel }}</span>
      </div>
    </td>
  </tr>
</table>
<hr>

{{-- ── BILLING INFO ── --}}
<table class="info-table">
  <tr>
    <td>
      <div class="info-section-title">{{ __('Tagihan Kepada') }}</div>
      <div class="info-row"><span class="info-val">{{ $order->user?->full_name ?? '-' }}</span></div>
      <div class="info-row"><span class="info-key">{{ __('Email:') }} </span><span class="info-val">{{ $order->user?->email ?? '-' }}</span></div>
      <div class="info-row"><span class="info-key">{{ __('WhatsApp:') }} </span><span class="info-val">{{ $order->user?->whatsapp ?: ($order->user?->phone ?: '-') }}</span></div>
    </td>
    <td>
      <div class="info-section-title">{{ __('Detail Acara') }}</div>
      <div class="info-row"><span class="info-key">{{ __('Tanggal Acara:') }} </span><span class="info-val">{{ \Carbon\Carbon::parse($order->booking_date)->format('d M Y') }}</span></div>
      @if($order->booking_time)
      <div class="info-row"><span class="info-key">{{ __('Waktu:') }} </span><span class="info-val">{{ \Carbon\Carbon::parse($order->booking_time)->format('H:i') }} {{ __('WIB') }}</span></div>
      @endif
      <div class="info-row"><span class="info-key">{{ __('Status Pesanan:') }} </span><span class="info-val">{{ $osLabel }}</span></div>
    </td>
  </tr>
</table>

{{-- ── ITEMS ── --}}
<div class="section-label">{{ __('Rincian Pesanan') }}</div>
<table class="items">
  <thead>
    <tr>
      <th style="width:80px;">{{ __('Gambar') }}</th>
      <th>{{ __('Item') }}</th>
      <th>{{ __('Qty') }}</th>
      <th class="right">{{ __('Harga Satuan') }}</th>
      <th class="right">{{ __('Subtotal') }}</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        @if($imgSrc)
          <img src="{{ $imgSrc }}" style="width:60px;height:60px;object-fit:cover;border-radius:3px;border:1px solid #eee;">
        @else
          <div style="width:60px;height:60px;background:#f0f0f0;border-radius:3px;"></div>
        @endif
      </td>
      <td>
        <div class="item-name">{{ $itemName }}</div>
        <div class="item-sub">{{ $itemType }} &bull; {{ $itemCat }}</div>
      </td>
      <td>{{ $order->quantity ?? 1 }}</td>
      <td class="right">Rp {{ number_format($order->total_price / max(1, $order->quantity ?? 1), 0, ',', '.') }}</td>
      <td class="right">Rp {{ number_format($order->total_price, 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>

{{-- ── TOTALS ── --}}
<table class="totals">
  <tr>
    <td class="label">{{ __('Subtotal') }}</td>
    <td class="value">Rp {{ number_format($order->total_price, 0, ',', '.') }}</td>
  </tr>
  @if($adminFee > 0)
  <tr>
    <td class="label">{{ __('Biaya Admin') }}</td>
    <td class="value">Rp {{ number_format($adminFee, 0, ',', '.') }}</td>
  </tr>
  @endif
  <tr class="grand">
    <td class="label">{{ __('Total') }}</td>
    <td class="value">Rp {{ number_format($order->total_price + $adminFee, 0, ',', '.') }}</td>
  </tr>
</table>

{{-- ── PAYMENT INFO ── --}}
<div class="section-label">{{ __('Informasi Pembayaran') }}</div>
<div class="payment-box">
  <table>
    <tr>
      <td class="key">{{ __('Payment Gateway') }}</td>
      <td class="val">{{ ucfirst($paymentGateway) }}</td>
      <td class="key" style="padding-left:20px;">{{ __('Metode') }}</td>
      <td class="val">{{ $paymentMethod !== '-' ? ucwords(str_replace('_', ' ', $paymentMethod)) : __('Belum dipilih') }}</td>
    </tr>
    <tr>
      <td class="key">{{ __('No. Referensi') }}</td>
      <td class="val">{{ $txRef }}</td>
      <td class="key" style="padding-left:20px;">{{ __('Status Transaksi') }}</td>
      <td class="val">{{ $txStatus }}</td>
    </tr>
    <tr>
      <td class="key">{{ __('Tanggal Bayar') }}</td>
      <td class="val">{{ $paidAt }}</td>
      <td class="key" style="padding-left:20px;">{{ __('Status Pembayaran') }}</td>
      <td class="val">{{ $psLabel }}</td>
    </tr>
  </table>
</div>

{{-- ── NOTES ── --}}
@if($order->notes)
<div class="section-label">{{ __('Catatan') }}</div>
<div class="notes-box">{{ strip_tags($order->notes) }}</div>
@endif

<hr class="light">

{{-- ── FOOTER ── --}}
<div class="footer">
  {{ str_replace([':appName', ':now'], [$appName, $now], __('Dokumen ini digenerate otomatis oleh sistem :appName pada :now.')) }}<br>
  {{ __('Invoice ini sah tanpa tanda tangan basah. Simpan dokumen ini sebagai bukti pembayaran Anda.') }}<br>
  {{ str_replace(':adminEmail', $adminEmail, __('Pertanyaan? Hubungi kami di :adminEmail.')) }}
</div>

</div>
</body>
</html>
