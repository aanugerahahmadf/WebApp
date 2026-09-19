<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? __('Verifikasi Kode') }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f7; color: #51545e; margin: 0; padding: 0; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 40px 0; }
        .content { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { background-color: #1a202c; padding: 30px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 1px; }
        .body { padding: 40px; }
        .body h2 { color: #333333; font-size: 20px; margin-top: 0; }
        .body p { font-size: 16px; line-height: 1.5em; color: #51545e; margin-bottom: 24px; }
        .otp-container { text-align: center; margin: 30px 0; }
        .otp-code { display: inline-block; font-size: 36px; font-weight: 700; color: #e11d48; letter-spacing: 10px; background-color: #ffe4e6; padding: 20px 30px; border-radius: 8px; }
        .footer { background-color: #f4f4f7; padding: 30px 40px; text-align: center; font-size: 13px; color: #a8aaaf; }
        .footer a { color: #e11d48; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content">
            <div class="header">
                <h1>{{ config('app.name', __('Wedding Flowers Decorasi')) }}</h1>
            </div>
            
            <div class="body">
                <h2>{{ $title ?? __('Kode Verifikasi') }}</h2>
                
                <p>{{ __('Halo') }},</p>
                <p>{{ $description ?? __('Silakan gunakan kode verifikasi di bawah ini untuk menyelesaikan tindakan Anda. Kode ini berlaku selama 30 menit ke depan.') }}</p>
                
                <div class="otp-container">
                    <div class="otp-code">{{ $otp }}</div>
                </div>
                
                <p>{{ __('Jika Anda tidak meminta kode ini, Anda dapat mengabaikan email ini dengan aman. Jangan bagikan kode ini kepada siapa pun.') }}</p>
                
                <p>{{ __('Terima kasih') }},<br>{{ __('Tim') }} {{ config('app.name') }}</p>
            </div>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Seluruh hak cipta dilindungi undang-undang.') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
