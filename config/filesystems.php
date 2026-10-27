<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // KYC documents and other private files must never be exposed by
            // Laravel's public local-file route.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
         * URL disk publik harus RELATIF ('/storage'), bukan `APP_URL/storage`.
         *
         * `APP_URL` dipakai ulang oleh tunnel script untuk menulis domain
         * trycloudflare yang sedang hidup, jadi isinya berubah-ubah: begitu
         * tunnel dimatikan, `APP_URL` masih menunjuk domain yang sudah mati.
         *
         * Dulu `'url' => env('APP_URL').'/storage'` jadi bom waktu: setiap URL
         * media di-absolukan ke domain tunnel yang mati, sehingga panel admin di
         * 127.0.0.1:8000 menampilkan Broken Image di kolom Foto. Yang gagal bukan
         * file-nya (storage/app/public berisi 2106 file, junction public/storage
         * juga ada) -- hanya hostnya yang salah.
         *
         * Media library dan asset() sama-sama bisa/training URL relatif, jadi
         * browser mencarinya relative terhadap origin yang sedang melayani
         * request: 127.0.0.1:8000 untuk panel lokal, IP LAN untuk HP, domain
         * tunnel untuk akses luar. Satu konfigurasi, benar di semua tempat.
         *
         * Untuk pemanggil yang butuh URL absolut (blade, respons API ke aplikasi
         * HP), `AppPlatform::normalizeUrl()` sudah mengubah URL relatif
         * `/storage/...` menjadi absolut terhadap origin request sekarang.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('MEDIA_URL', '/storage'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Firebase Storage (Google Cloud Storage) — gratis 5 GB, auto-scaling
        // Butuh: composer require superbalist/laravel-google-cloud-storage
        'firebase' => [
            'driver' => 'google-cloud-storage',
            'project_id' => env('FIREBASE_PROJECT_ID'),
            'key_file' => env('FIREBASE_CREDENTIALS', storage_path('keys/firebase-service-account.json')),
            'bucket' => env('FIREBASE_STORAGE_BUCKET'),
            'path_prefix' => env('FIREBASE_STORAGE_PATH_PREFIX', 'uploads'),
            'visibility' => 'public',
            'metadata' => [
                'cacheControl' => 'public, max-age=31536000',
            ],
        ],

        // Disk gabungan: simpan lokal dulu, lalu sync ke Firebase di background
        // Pakai untuk upload user (Packages, Catalogs, review-photos)
        // Sama seperti disk `public`: URL relatif, bukan APP_URL/storage.
        // Lihat catatan panjang di disk `public` di atas untuk alasannya.
        'public_firebase' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('MEDIA_URL', '/storage'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
