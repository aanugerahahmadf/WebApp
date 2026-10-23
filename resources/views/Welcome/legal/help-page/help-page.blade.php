<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    {{-- Gaya Teks & tipografi: CSS dari setelan admin (AppFont dengan
         per-peran AppTypography + override per-field TextStyles) ke SETIAP
         halaman, bukan hanya panel. --}}
    {!! \App\Support\AppFont\AppFont::styles() !!}

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Google Sans Flex', sans-serif;
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
            font-weight: 700;
            color: #0f172a;
        }
        .header p {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
        .header .updated {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }
        .faq-section {
            margin-top: 24px;
        }
        .faq-section h2 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .faq {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .faq .q {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .faq .a {
            font-size: 14px;
            color: #475569;
            text-align: justify;
        }
        .contact {
            margin-top: 24px;
        }
        .contact h2 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .contact-options {
            list-style: none;
        }
        .contact-options li {
            background: #fff;
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            font-size: 14px;
            color: #334155;
        }
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
            {{-- data-field: override per-field dari form admin; data-typo: per-peran. --}}
            <h1>{{ $title }}</h1>
            @if ($subtitle)
                <p data-typo="subheading">{{ $subtitle }}</p>
            @endif
            @if ($updatedAt)
                <div class="updated">{{ __('Terakhir diperbarui:') }} {{ $updatedAt->format('d M Y') }}</div>
            @endif
        </div>

        <div class="faq-section">
            <h2 data-typo="subheading">{{ __('FAQ') }}</h2>
            @forelse ($faqs as $item)
                <div class="faq">
                    <div class="q" data-typo="subheading">{{ $item['question'] ?? $item['q'] ?? $item['heading'] ?? '' }}</div>
                    <div class="a" data-typo="description">{{ $item['answer'] ?? $item['a'] ?? $item['body'] ?? '' }}</div>
                </div>
            @empty
                <div class="empty">{{ __('Konten belum tersedia.') }}</div>
            @endforelse
        </div>

        @if (! empty($contactOptions))
            <div class="contact">
                <h2>{{ __('Kontak') }}</h2>
                <ul class="contact-options">
                    @foreach ($contactOptions as $key => $option)
                        @php
                            // Dua bentuk data yang mungkin tersimpan:
                            //   1. daftar objek: ['label' => 'WhatsApp Support', 'subLabel' => '+62 ...', 'url' => ...]
                            //   2. peta label => nilai: ['Email' => 'support@...']
                            // Bentuk 1 TIDAK boleh di-echo mentah -- htmlspecialchars()
                            // menolak array dan halaman jatuh 500.
                            if (is_array($option)) {
                                $contactLabel = $option['label'] ?? $option['name'] ?? (is_string($key) ? $key : '');
                                $contactValue = $option['subLabel'] ?? $option['sub_label'] ?? $option['value'] ?? $option['subtitle'] ?? '';
                                $contactUrl = $option['url'] ?? $option['href'] ?? null;
                            } else {
                                $contactLabel = is_string($key) ? $key : '';
                                $contactValue = (string) $option;
                                $contactUrl = null;
                            }
                        @endphp
                        @if ($contactLabel !== '' || $contactValue !== '')
                            <li>
                                @if ($contactLabel !== '')<strong>{{ $contactLabel }}:</strong>@endif
                                @if ($contactUrl)
                                    <a href="{{ $contactUrl }}">{{ $contactValue !== '' ? $contactValue : $contactUrl }}</a>
                                @else
                                    {{ $contactValue }}
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </div>
    </div>
</body>
</html>