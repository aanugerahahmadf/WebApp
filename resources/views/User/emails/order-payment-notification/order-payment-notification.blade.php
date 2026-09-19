<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Notifikasi Pembayaran') }}</title>
<style>
  body  { margin:0; padding:0; background:#f5f5f5; font-family:Arial,sans-serif; font-size:14px; color:#333; }
  .wrap { max-width:600px; margin:24px auto; background:#fff; border:1px solid #e0e0e0; }

  .logo-bar { text-align:center; padding:24px 0 20px; border-bottom:1px solid #e0e0e0; }
  .logo-bar img { height:48px; width:auto; }
  .logo-bar .app-name { font-size:20px; font-weight:bold; color:#111; }

  .body { padding:28px 32px; }
  .body p { margin:0 0 12px; line-height:1.6; font-size:14px; }

  .status-bar { padding:10px 16px; font-size:13px; font-weight:bold; margin-bottom:20px; border-radius:3px; }
  .status-paid    { background:#e8f5e9; color:#2e7d32; border-left:4px solid #43a047; }
  .status-pending { background:#fff8e1; color:#e65100; border-left:4px solid #ffb300; }
  .status-failed  { background:#ffebee; color:#c62828; border-left:4px solid #e53935; }

  .section-title { font-size:12px; font-weight:bold; color:#111; text-transform:uppercase;
                   letter-spacing:0.5px; border-bottom:2px solid #111; padding-bottom:6px;
                   margin:20px 0 14px; }

  table.detail { width:100%; border-collapse:collapse; margin-bottom:20px; }
  table.detail td { padding:7px 0; font-size:13px; border-bottom:1px solid #f0f0f0; }
  table.detail td.key { color:#666; width:45%; }
  table.detail td.val { font-weight:bold; color:#111; }



  .btn-wrap { text-align:center; margin:24px 0 8px; }
  .btn { display:inline-block; background:#111; color:#fff !important; text-decoration:none;
         padding:11px 32px; border-radius:3px; font-size:13px; font-weight:bold; }

  .footer { background:#f5f5f5; padding:16px 32px; font-size:11px; color:#999;
            border-top:1px solid #e0e0e0; text-align:center; line-height:1.8; }
</style>
</head>
<body>

@php
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;

$ps = $order->payment_status instanceof OrderPaymentStatus
    ? $order->payment_status
    : OrderPaymentStatus::tryFrom((string) $order->payment_status);

$statusVal   = $ps?->value ?? '';
$isPaid      = in_array($statusVal, ['paid', 'partial']);
$isFailed    = in_array($statusVal, ['failed']);
$statusLabel = $ps?->getLabel() ?? $statusVal;
$statusClass = $isFailed ? 'status-failed' : ($isPaid ? 'status-paid' : 'status-pending');
$statusText  = $isFailed
    ? __('Pembayaran Gagal')
    : ($isPaid ? __('Pembayaran Berhasil') : __('Menunggu Pembayaran'));

$item      = $order->package ?? $order->product;
$itemType  = $order->package_id
    ? \App\Filament\User\Resources\PackageResource\PackageResource::getModelLabel()
    : \App\Filament\User\Resources\ProductResource\ProductResource::getModelLabel();
$itemName  = $item?->name ?? '-';
$itemCat   = $item?->category?->name ?? '-';
$itemPrice = $order->total_price;
$qty       = $order->quantity ?? 1;

$os = $order->status instanceof OrderStatus
    ? $order->status
    : OrderStatus::tryFrom((string) $order->status);
$orderStatusLabel = $os?->getLabel() ?? (string) $order->status;

$orderUrl   = config('app.url') . '/user/orders';
$appName    = config('app.name', 'Wedding Flowers Decorasi');
$userName   = $user->full_name ?? $user->username ?? __('Pelanggan');
$adminEmail = \App\Models\User\User::whereHas('roles', fn($q) => $q->where('name','super_admin'))
    ->value('email') ?? config('mail.from.address');

$bookingDate = $order->booking_date
    ? \Carbon\Carbon::parse($order->booking_date)->translatedFormat('d F Y')
    : '-';
$bookingTime = $order->booking_time ?? '-';

// Dari Mailable::build() — URL publik atau base64 kecil
$imgSrc  = $itemImageSrc ?? null;
$logoSrc = $logoSrc      ?? null;
@endphp

<div class="wrap">

  {{-- Logo --}}
  <div class="logo-bar">
    @if($logoSrc)
      <img src="{{ $logoSrc }}" alt="{{ $appName }}">
    @else
      <span class="app-name">{{ $appName }}</span>
    @endif
  </div>

  <div class="body">

    <div class="status-bar {{ $statusClass }}">{{ $statusText }}</div>

    <p>{{ __('Hai') }} <strong>{{ $userName }},</strong></p>

    @if($isPaid)
      <p>{{ __('Pembayaran Anda untuk pesanan berikut telah berhasil dikonfirmasi.') }}</p>
    @elseif($isFailed)
      <p>{{ __('Pembayaran Anda tidak berhasil diproses. Silakan coba lagi atau hubungi kami.') }}</p>
    @else
      <p>{{ __('Pesanan Anda belum dibayar. Segera selesaikan pembayaran agar pesanan dapat diproses.') }}</p>
    @endif

    {{-- Produk/Paket yang dipesan seperti marketplace --}}
    <table style="width:100%; border-collapse:collapse; margin:16px 0; background:#fafafa; border-radius:8px; overflow:hidden;">
      <tr>
        <td style="width:80px; padding:12px; vertical-align:top;">
          @if($imgSrc)
            <img src="{{ $imgSrc }}" alt="{{ $itemName }}" style="width:72px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #e0e0e0;">
          @else
            <div style="width:72px; height:72px; background:#eee; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;">No Image</div>
          @endif
        </td>
        <td style="padding:12px 12px 12px 0; vertical-align:top;">
          <div style="font-size:11px; color:#888; margin-bottom:2px;">{{ $itemType }}</div>
          <div style="font-size:14px; font-weight:bold; color:#111; margin-bottom:4px;">{{ $itemName }}</div>
          <div style="font-size:11px; color:#888;">{{ $itemCat }} &times; {{ $qty }}</div>
          <div style="font-size:15px; font-weight:bold; color:#111; margin-top:6px;">Rp {{ number_format($itemPrice, 0, ',', '.') }}</div>
        </td>
      </tr>
    </table>

    <div class="section-title">{{ __('Rincian Pesanan') }}</div>

    <table class="detail">
      <tr>
        <td class="key">{{ __('No. Pesanan') }}</td>
        <td class="val">#{{ $order->order_number }}</td>
      </tr>
      <tr>
        <td class="key">{{ __('Tanggal Booking') }}</td>
        <td class="val">{{ $bookingDate }} {{ $bookingTime }}</td>
      </tr>
      <tr>
        <td class="key">{{ __('Jumlah Item') }}</td>
        <td class="val">{{ $qty }} {{ $qty > 1 ? __('item') : __('item') }}</td>
      </tr>
      <tr>
        <td class="key">{{ __('Total Pembayaran') }}</td>
        <td class="val" style="font-size:16px;">Rp {{ number_format($itemPrice, 0, ',', '.') }}</td>
      </tr>
      <tr>
        <td class="key">{{ __('Status Pembayaran') }}</td>
        <td class="val">{{ $statusLabel }}</td>
      </tr>
      <tr>
        <td class="key">{{ __('Status Pesanan') }}</td>
        <td class="val">{{ $orderStatusLabel }}</td>
      </tr>
    </table>



    <div class="btn-wrap">
      <a href="{{ $orderUrl }}" class="btn">{{ __('Lihat Pesanan') }}</a>
    </div>

  </div>

  <div class="footer">
    {{ __('Email ini dikirim otomatis oleh sistem :app. Jangan balas email ini.', ['app' => $appName]) }}<br>
    {{ __('Pertanyaan? Hubungi kami di') }} {{ $adminEmail }}
  </div>

</div>
</body>
</html>
