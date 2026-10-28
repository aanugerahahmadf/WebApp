<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BRI API Configuration
    |--------------------------------------------------------------------------
    |
    | Kredensial dibuat di https://developers.bri.co.id (bmr.bri.co.id),
    | menu Aplikasi Saya -> Aplikasi BRI API.
    |
    | Legacy BRI API: HMAC-SHA512 dengan payload:
    |   path={path}&verb={verb}&token={token}&timestamp={timestamp}&body={body}
    | SNAP BI: B2B token via RSA (asymmetric), SNAP API calls via HMAC-SHA512.
    |
    */

    'enabled' => (bool) env('BRI_ENABLED', false),

    'client_id' => env('BRI_CLIENT_ID', ''),
    'client_secret' => env('BRI_CLIENT_SECRET', ''),

    // Tipe Snap Key: "asymmetric" (RSA) atau "symmetric" (HMAC).
    // Symmetric hanya butuh client_secret, asymmetric perlu RSA private key.
    'snap_key_type' => env('BRI_SNAP_KEY_TYPE', 'symmetric'),

    // Rekening settlement merchant. Semua dana yang masuk ke nomor Virtual
    // Account pelanggan diteruskan BRI ke rekening ini, dan API statement
    // dipakai untuk mencocokkan mutasinya.
    //
    // Nilainya dijadikan default (bukan string kosong) karena rekening ini
    // adalah bagian dari alur pembayaran toko: tanpa nilai default, instalasi
    // baru yang lupa mengisi .env akan "lupa" ke mana uang harus masuk.
    // JANGAN pernah memakai nilai ini sebagai nomor VA yang ditampilkan ke
    // pelanggan -- nomor VA dibuat unik per user + pesanan.
    'account_number' => env('BRI_ACCOUNT_NUMBER', '421201032041536'),
    'account_holder' => env('BRI_ACCOUNT_HOLDER', 'Anugerah Ahmad Fachrurochim'),

    // Base URL. Sandbox: https://sandbox.partner.api.bri.co.id
    // Production: https://partner.api.bri.co.id
    'base_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id'),

    // Endpoint (otomatis mengikuti base_url)
    'token_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/oauth/client_credential/accesstoken',
    'statement_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/v2.0/statement',
    'inquiry_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/v2/inquiry',

    // Jendela waktu mutasi yang ditarik pada tiap pengecekan (hari ke belakang)
    'lookback_days' => (int) env('BRI_LOOKBACK_DAYS', 7),

    // Toleransi selisih nominal saat mencocokkan transaksi (Rupiah)
    'amount_tolerance' => (float) env('BRI_AMOUNT_TOLERANCE', 500),

    // Maksimal mutasi yang diproses sekali jalan (pelindung beban API)
    'max_rows' => (int) env('BRI_MAX_ROWS', 200),

    /*------------------------------------------------------------------------
    | BRI Virtual Account (BRIVA) - SNAP BI
    |------------------------------------------------------------------------
    | Aktifkan pembayaran Virtual Account. Uang masuk otomatis ke rekening
    | admin via webhook notifikasi pembayaran BRI (tanpa admin verifikasi).
    |
    | CATATAN PENTING:
    | 1. Sandbox saat ini hanya SIMULASI - produk virtual-account-snap.
    | 2. Endpoint B2B token (create-va) WAJIB RSA private key dari menu
    |    "Manage Snap Key" di developers.bri.co.id. Letakkan di folder
    |    storage/keys/snap-private.pem dan isi BRI_SNAP_PRIVATE_KEY.
    | 3. CREDENTIAL PEMBAYARAN AKAN MASUK KE REKENING ADMIN (nominal sama).
    |
    */

    // Aktifkan fitur Virtual Account (BRIVA).
    'snap_enabled' => (bool) env('BRI_SNAP_ENABLED', false),

    // When true, webhook signature verification is ALWAYS enforced regardless of snapEnabled().
    // Set to true in production to prevent accepting unverified webhook payloads.
    'webhook_require_signature' => (bool) env('BRI_WEBHOOK_REQUIRE_SIGNATURE', true),

    // Path ke RSA private key (.pem) untuk signature endpoint B2B token (hanya untuk tipe asymmetric).
    // Contoh: base_path('storage/keys/snap-private.pem')
    'snap_private_key' => env('BRI_SNAP_PRIVATE_KEY', ''),

    // Kode partner / BIN perusahaan (8 digit). Untuk sandbox simulasi BRI:
    // gunakan 8808. Di production diganti kode BRIVA yang didaftarkan.
    'va_partner_service_id' => env('BRI_VA_PARTNER_SERVICE_ID', '8808'),

    // Channel ID SNAP untuk VA (5-digit numeric). Referensi:
    // 00001: teller, 00002: ATM, 00003: IB/NBMB/Brilink Mobile,
    // 00009: API (Host To Host).
    'va_channel_id' => env('BRI_VA_CHANNEL_ID', '00009'),

    // Masa berlaku nomor VA (jam).
    'va_expiry_hours' => (int) env('BRI_VA_EXPIRY_HOURS', 24),

    /*
     * Path yang dipakai BRI saat menghitung X-SIGNATURE notifikasi pembayaran
     * (SNAP: HMAC-SHA512 atas METHOD:EndpointUrl:AccessToken:bodyHash:Timestamp).
     *
     * BRI tidak mengirim path itu di body, jadi kita coba beberapa kandidat
     * endpoint resmi. Tanpa ini, notifikasi yang sah dari BRI ditolak dan
     * pesanan tidak pernah otomatis LUNAS. Verifikasi tetap memakai
     * client_secret; daftar ini hanya menyangkut stringToSign.
     */
    'notification_paths' => [
        '/v1.0/transfer-va/payment',
        '/snap/v1.0/transfer-va/payment',
        '/v1.0/transfer-va/payment-inquiry',
        '/v1.0/qr/qr-mpm-notify',
        '/snap/v1.0/qr/qr-mpm-notify',
    ],

    // Endpoint SNAP
    'snap_token_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/snap/v1.0/access-token/b2b',
    'va_create_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/snap/v1.0/transfer-va/create-va',

    /*------------------------------------------------------------------------
    | BRI QRIS Dinamis (MPM) - SNAP BI
    |------------------------------------------------------------------------
    | 1 pesanan = 1 kode QR dinamis yang bisa discan dari aplikasi mana pun
    | (GoPay, DANA, OVO, ShopeePay, m-banking). Dana masuk rekening BRI admin
    | via webhook. Data merchant diambil dari halaman QRIS BRI (merchant id /
    | PAN 18 digit, terminal label, dll).
    |
    */
    'qr_enabled' => (bool) env('BRI_QR_ENABLED', false),

    // 18 digit Merchant ID / PAN yang diberikan BRI saat aktivasi QRIS.
    'qr_merchant_id' => env('BRI_QR_MERCHANT_ID', ''),
    'qr_terminal_label' => env('BRI_QR_TERMINAL_LABEL', 'WO01'),
    'qr_merchant_name' => env('BRI_QR_MERCHANT_NAME', 'Dekorasi Bunga Pernikahan'),
    'qr_merchant_city' => env('BRI_QR_MERCHANT_CITY', 'JAKARTA'),
    'qr_merchant_postal_code' => env('BRI_QR_MERCHANT_POSTAL_CODE', '10560'),
    'qr_merchant_province' => env('BRI_QR_MERCHANT_PROVINCE', 'DKI JAKARTA'),
    'qr_merchant_country' => env('BRI_QR_MERCHANT_COUNTRY', 'ID'),

    // Masa berlaku QR (menit)
    'qr_expiry_minutes' => (int) env('BRI_QR_EXPIRY_MINUTES', 1440),

    'qr_create_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id').'/snap/v1.0/qr/qr-mpm-generate',
];
