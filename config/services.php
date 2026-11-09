<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai_core_url' => env('AI_CORE_URL', 'http://127.0.0.1:5000'),
    'ai_core_api_key' => env('AI_CORE_API_KEY'),
    'ai_core_timeout' => env('AI_CORE_TIMEOUT', 15),

    /*
     * Apakah operasi TULIS ke indeks CBIR (tambah/hapus entri) boleh jalan.
     *
     * Test suite memakai QUEUE_CONNECTION=sync, jadi `IndexMediaToCBIR`
     * berjalan langsung saat sebuah media dibuat, dan `MediaObserver::deleted`
     * langsung menghapus entri. Kalau AI Core hidup di mesin yang sama, satu
     * kali `php artisan test` akan menulis gambar palsu ke
     * `ai_core/data/metadata.json` dan menghapus entri sungguhan — indeks
     * menyusut tanpa error apa pun, dan hasil pencarian jadi salah.
     *
     * Karena itu penulisan dimatikan otomatis saat `runningUnitTests()`.
     * Set `AI_CORE_WRITES_IN_TESTS=true` hanya bila memang sedang menguji
     * jalur tulis terhadap AI Core palsu (Http::fake).
     */
    'ai_core_writes_in_tests' => env('AI_CORE_WRITES_IN_TESTS', false),
    'ai_chat_context_messages' => env('AI_CHAT_CONTEXT_MESSAGES', 16),

    // Free, self-hosted conversational model. Ollama must run on the same
    // machine; disabled by default so the deterministic fallback remains
    // available during development and tests.
    'ai_local_enabled' => env('AI_LOCAL_ENABLED', false),
    'ai_local_url' => env('AI_LOCAL_URL', 'http://127.0.0.1:11434'),
    'ai_local_model' => env('AI_LOCAL_MODEL', 'qwen2.5:3b'),
    'ai_local_timeout' => env('AI_LOCAL_TIMEOUT', 90),

    'turn' => [
        'url' => env('TURN_SERVER_URL', 'turn:127.0.0.1:3478'),
        'username' => env('TURN_USERNAME', 'weddingapp'),
        'credential' => env('TURN_CREDENTIAL', 'change-me-now'),
        'realm' => env('TURN_REALM', 'wedding.local'),
    ],
    'cbir_min_similarity' => env('CBIR_MIN_SIMILARITY', 30.0),
    'cbir_api_url' => env('CBIR_API_URL', 'http://127.0.0.1:5000'),

    /*
     * Transport gRPC ke AI Core.
     *
     * `AI_CORE_URL` di atas tetap dipakai sebagai jalur cadangan (Flask).
     * Kalau AI Core dijalankan sebagai server gRPC saja, HTTP tidak akan
     * pernah menjawab, jadi gRPC-lah jalur utama. Host sengaja TANPA skema:
     * gRPC memakai `host:port`, bukan URL.
     */
    'ai_core_grpc_enabled' => env('AI_CORE_GRPC_ENABLED', true),
    'ai_core_grpc_host' => env('AI_CORE_GRPC_HOST', '127.0.0.1:15052'),
    /*
     * 120 detik, bukan 15. Panggilan PERTAMA ke server gRPC memuat model
     * ekstraksi (torch) + `data/metadata.json` 20 MB, dan pada mesin ini
     * makan ~49 detik. Timeout 15 detik membuat pencarian pertama SELALU
     * gagal dengan "Deadline Exceeded" lalu jatuh ke HTTP yang juga mati,
     * padahal panggilan berikutnya hanya ~0,4 detik.
     */
    'ai_core_grpc_timeout_ms' => env('AI_CORE_GRPC_TIMEOUT_MS', 120000),

    // WhatsApp via Fonnte API — https://fonnte.com
    'fonnte_token' => env('FONNTE_TOKEN', ''),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),

        /*
         * Google Maps Platform untuk pemilih alamat di checkout
         * (App\Forms\Components\CheckoutAddress). Satu key ini mengaktifkan
         * Maps JavaScript API, Places API (Autocomplete + Place Details), dan
         * Geocoder untuk membalik koordinat GPS menjadi alamat.
         *
         * PENTING: key ini terekspos ke browser (harus ada Referer restriction),
         * jadi jangan pernah memakai key yang juga memegang kuota server-side
         * seperti Google Sheets di bawah. Kalau kosong, komponennya tetap
         * berfungsi: peta dan autocomplete dinonaktifkan, tetapi GPS + pengetikan
         * manual tetap jalan.
         */
        'maps_key' => env('GOOGLE_MAPS_API_KEY', ''),
    ],

    // Google Sheets untuk cadangan data review.
    // Sheet hanya menampung teks -- file foto tidak bisa diunggah ke sini,
    // yang dicatat hanyalah path dan URL publiknya.
    'google_sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID', ''),
        'service_account_json' => env('GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON', ''),
        'tab' => env('GOOGLE_SHEETS_TAB', 'ReviewsBackup'),
    ],

    /*
     * Google Apps Script Web App — saluran sinkron yang sama dipakai Laravel,
     * Flutter, Kotlin, dan AI Core (lihat apps_script/README.md).
     *
     * Ini BUKAN sumber kebenaran: harga, status order, expiry pembayaran, dan
     * keputusan KYC tetap dari database Laravel (AGENTS.md §2). Kegagalan
     * Apps Script tidak boleh menjatuhkan alur bisnis.
     *
     * URL wajib versi /exec bukan /dev, dan secret-nya WAJIB sama dengan
     * WFD_SHARED_SECRET di Script Properties. Kredensial dikirim di BODY,
     * bukan header: Web App Apps Script tidak bisa membaca custom HTTP header.
     */
    'google_apps_script' => [
        'enabled' => filter_var(env('GOOGLE_APPS_SCRIPT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'exec_url' => env('GOOGLE_APPS_SCRIPT_EXEC_URL', ''),
        'secret' => env('GOOGLE_APPS_SCRIPT_SECRET', ''),
        'read_key' => env('GOOGLE_APPS_SCRIPT_READ_KEY', ''),
        'source' => env('GOOGLE_APPS_SCRIPT_SOURCE', 'laravel'),
        'timeout' => (int) env('GOOGLE_APPS_SCRIPT_TIMEOUT', 30),
        'attempts' => (int) env('GOOGLE_APPS_SCRIPT_ATTEMPTS', 2),

        // Modul yang ikut terkirim otomatis saat siklus bisnis berlangsung.
        // Dimatikan satu per satu kalau kuota Apps Script jadi kendala.
        'mirror' => [
            'orders' => filter_var(env('GOOGLE_APPS_SCRIPT_MIRROR_ORDERS', true), FILTER_VALIDATE_BOOLEAN),
            'reviews' => filter_var(env('GOOGLE_APPS_SCRIPT_MIRROR_REVIEWS', true), FILTER_VALIDATE_BOOLEAN),
            'catalog' => filter_var(env('GOOGLE_APPS_SCRIPT_MIRROR_CATALOG', false), FILTER_VALIDATE_BOOLEAN),
            'kyc' => filter_var(env('GOOGLE_APPS_SCRIPT_MIRROR_KYC', true), FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    'geonames' => [
        'username' => env('GEONAMES_USERNAME'),
    ],

    'clerk_sync_secret' => env('CLERK_SYNC_SECRET', ''),

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY', ''),
    ],
];
