<?php

namespace App\Mail\OtpMail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    public string $name;

    public function __construct(string $otp, string $name = 'Pengguna')
    {
        $this->otp = $otp;
        $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi OTP - Dekorasi Bunga Pernikahan',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table style="max-width:480px; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);">
<tr><td style="padding:30px 24px 20px; text-align:center; background:linear-gradient(135deg,#6C63FF,#E040FB);">
<h1 style="color:#fff; margin:0; font-size:22px;">Dekorasi Bunga Pernikahan</h1>
</td></tr>
<tr><td style="padding:30px 24px; text-align:center;">
<h2 style="margin:0 0 8px; color:#333; font-size:18px;">Halo, {$this->name}!</h2>
<p style="color:#666; font-size:14px; margin:0 0 20px;">Gunakan kode OTP berikut untuk verifikasi akun Anda:</p>
<div style="background:#f0f4ff; border-radius:10px; padding:16px; letter-spacing:8px; font-size:32px; font-weight:bold; color:#6C63FF;">{$this->otp}</div>
<p style="color:#999; font-size:12px; margin-top:20px;">Kode ini berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.</p>
</td></tr>
<tr><td style="padding:16px 24px; text-align:center; background:#f8f8f8;">
<p style="color:#999; font-size:11px; margin:0;">&copy; 2026 Dekorasi Bunga Pernikahan. All rights reserved.</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
    }

    public function attachments(): array
    {
        return [];
    }
}
