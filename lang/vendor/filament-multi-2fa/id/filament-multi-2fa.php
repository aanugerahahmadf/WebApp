<?php

/**
 * Bahasa Indonesia untuk mix-code/filament-multi-2fa.
 *
 * Paket hanya membawa 'en' dan 'ar'. Tanpa file ini, SETIAP teks dari paket
 * tampil sebagai kunci mentahnya -- "filament-multi-2fa::filament-multi-2fa.2fa_setup"
 * -- di halaman setup 2FA, verifikasi OTP, dan tempat lain. Baru ketahuan kalau
 * lokalenya Indonesia; di browser berbahasa Inggris semua teksnya tampil
 * normal karena cabang 'en'-nya memang ada.
 *
 * Letak mengikuti konvensi Laravel untuk override bahasa paket:
 * lang/vendor/{namespace}/{locale}/{file}.php
 *
 * Kunci-kunci di sini SALINAN persis dari en milik paket. Kalau paket
 * menambah kunci baru, file ini tidak ikut -- itu memang risikonya, dan
 * gejalanya jelas: teks fallback-nya kembali jadi kunci mentah.
 */

return [
    'email' => 'Email',
    'totp' => 'Aplikasi Autentikasi',
    'none' => 'Tidak Ada',
    'your_verification_code' => 'Kode Verifikasi',
    'your_otp' => 'Kode OTP Anda adalah :code',
    'expires_in' => 'Kedaluwarsa dalam :minutes menit',
    'setup' => 'Atur',
    '2fa_setup' => 'Atur Autentikasi Dua Faktor',
    'two_factor_setup' => 'Atur Dua Faktor',
    'two_factor_setup_intro' => 'Tambahkan lapisan keamanan ekstra pada akun Anda.',
    'two_factor_setup_intro2' => 'Pilih cara Anda ingin memverifikasi akun.',
    'verified_before' => 'Anda pernah memverifikasi akun dengan metode ini sebelumnya.',
    'must_setup_2fa' => 'Anda wajib mengatur Autentikasi Dua Faktor.',
    'two_factor_type' => 'Pilih Jenis Autentikasi Dua Faktor',
    'otp' => 'Kode OTP',
    'wrong_otp' => 'Kode OTP salah',
    'verify' => 'Verifikasi',
    'trust_device' => 'Ingatkan Perangkat Ini',
    'otp_has_sent_to_your_email' => 'Kode OTP telah dikirim ke email Anda.',
    'get_otp_from_authenticator_app' => 'Ambil Kode dari Aplikasi Autentikasi',
    'resend_otp' => 'Kirim Ulang OTP',
    'resend_available_in' => 'Kode OTP bisa dikirim ulang dalam :minutes menit',
    'resend_available_in_seconds' => 'Kode OTP bisa dikirim ulang dalam :seconds detik',
    'resend_available_in_less_than_minute' => 'Kode OTP bisa dikirim ulang dalam waktu kurang dari satu menit',
    'cancel' => 'Batal',
];