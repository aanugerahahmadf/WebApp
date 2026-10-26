<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    {{-- Gaya Teks & tipografi: CSS dari setelan admin (AppFont dengan
         per-peran AppTypography + override per-field TextStyles) ke SETIAP
         halaman, bukan hanya panel -- supaya Kapital/perataan/font yang
         diatur di form Admin ikut ke sini juga (per-field menang). --}}
    {!! \App\Support\AppFont\AppFont::styles() !!}

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Instrument Sans', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .header {
            text-align: center;
            padding: 32px 0 8px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 600;
            color: #0f172a;
        }
        .header .updated {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
        }
        .content {
            margin-top: 24px;
        }
        .section {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .section h2 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .section p {
            font-size: 14px;
            color: #475569;
            text-align: justify;
        }
        .section.italic p { font-style: italic; }
        .empty {
            text-align: center;
            padding: 48px 16px;
            color: #94a3b8;
            font-style: italic;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            padding: 16px;
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{-- data-field: override per-field "title"/"content"; data-typo: tipografi per-peran. --}}
            <h1>{{ $title }}</h1>
            @if ($updatedAt)
                <div class="updated">{{ __('Terakhir diperbarui:') }} {{ $updatedAt->format('d M Y') }}</div>
            @endif
        </div>

        <div class="content">
            @forelse ($sections as $i => $item)
                <div class="section @if(!empty($item['is_italic'])) italic @endif">
                    <h2 data-typo="subheading">{{ $i + 1 }}. {{ $item['heading'] ?? '' }}</h2>
                    <p data-typo="description">{{ $item['body'] ?? '' }}</p>
                </div>
            @empty
                <div class="empty">{{ __('Konten belum tersedia.') }}</div>
            @endforelse
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </div>
    </div>
</body>
</html>
