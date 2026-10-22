<?php

use App\Models\FmAppSetting\FmAppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $settings = [
            'maintenance_mobile_flutter_android' => [
                'panel' => 'Mobile Android (Flutter)',
                'is_active' => false,
                'message' => 'Aplikasi Android sedang dalam pemeliharaan berkala untuk peningkatan fitur dan performa. Silakan coba kembali nanti.',
                'bypass_secret' => 'bypass-flutter-android',
                'allowed_ips' => '127.0.0.1',
                'http_status' => 503,
            ],
            'maintenance_mobile_flutter_ios' => [
                'panel' => 'Mobile iOS (Flutter)',
                'is_active' => false,
                'message' => 'Aplikasi iOS sedang dalam pemeliharaan berkala untuk peningkatan fitur dan performa. Silakan coba kembali nanti.',
                'bypass_secret' => 'bypass-flutter-ios',
                'allowed_ips' => '127.0.0.1',
                'http_status' => 503,
            ],
            'maintenance_mobile_capacitor_android' => [
                'panel' => 'Mobile Android (Capacitor)',
                'is_active' => false,
                'message' => 'Aplikasi Android (Capacitor Shell) sedang dalam pemeliharaan sistem. Silakan coba kembali nanti.',
                'bypass_secret' => 'bypass-capacitor-android',
                'allowed_ips' => '127.0.0.1',
                'http_status' => 503,
            ],
            'maintenance_mobile_capacitor_ios' => [
                'panel' => 'Mobile iOS (Capacitor)',
                'is_active' => false,
                'message' => 'Aplikasi iOS (Capacitor Shell) sedang dalam pemeliharaan sistem. Silakan coba kembali nanti.',
                'bypass_secret' => 'bypass-capacitor-ios',
                'allowed_ips' => '127.0.0.1',
                'http_status' => 503,
            ],
            'mobile_flutter' => [
                'app_name' => 'Dekorasi Bunga Pernikahan',
                'tagline' => 'Pemesanan & Rekomendasi Dekorasi Bunga Berbasis AI',
                'primary_color' => '#6366f1',
                'splash_tagline' => 'Wujudkan Dekorasi Impian Anda',
                'play_store_url' => 'https://play.google.com/store/apps/details?id=com.weddingflowers.app',
                'app_store_url' => 'https://apps.apple.com/app/wedding-flowers/id123456789',
                'min_app_version' => '1.0.0',
                'force_update' => false,
            ],
            'mobile_capacitor' => [
                'app_name' => 'Dekorasi Bunga Mobile Shell',
                'tagline' => 'Portal Mobile Terintegrasi Android & iOS',
                'primary_color' => '#4f46e5',
                'splash_tagline' => 'Portal Layanan Cepat',
                'play_store_url' => 'https://play.google.com/store/apps/details?id=id.dekorasi.pengantin.admin',
                'app_store_url' => 'https://apps.apple.com/app/dekorasi-admin/id987654321',
                'min_app_version' => '1.0.0',
                'force_update' => false,
            ],
        ];

        foreach ($settings as $key => $value) {
            FmAppSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    public function down(): void
    {
        FmAppSetting::whereIn('key', [
            'maintenance_mobile_flutter_android',
            'maintenance_mobile_flutter_ios',
            'maintenance_mobile_capacitor_android',
            'maintenance_mobile_capacitor_ios',
            'mobile_flutter',
            'mobile_capacitor',
        ])->delete();
    }
};
