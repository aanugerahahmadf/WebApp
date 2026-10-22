<?php

namespace App\Http\Controllers\Api\User\AppSettingsController;

use App\Http\Controllers\Controller;
use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppPlatform\AppPlatform;
use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * App config untuk mobile (tanpa auth).
 * Semua teks/URL branding wajib dari backend Filament, bukan template di app.
 */
class AppSettingsController extends Controller
{
    public function index(Request $request)
    {
        $config = Config::get('wedding_app', []);
        $appearance = \App\Support\AppFont\AppFont::settings();

        $platformHeader = strtolower((string) ($request->header('X-Platform') ?? $request->header('X-Capacitor') ?? $request->header('X-Shell') ?? $request->query('platform') ?? ''));
        $userAgent = strtolower((string) $request->userAgent());

        // Identifikasi target platform mobile secara presisi
        $maintenanceKey = 'maintenance_user';
        $mobileCustomKey = 'mobile_flutter';

        if (str_contains($platformHeader, 'flutter') || str_contains($userAgent, 'dart') || str_contains($userAgent, 'flutter')) {
            $mobileCustomKey = 'mobile_flutter';
            if (str_contains($platformHeader, 'ios') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad')) {
                $maintenanceKey = 'maintenance_mobile_flutter_ios';
            } else {
                $maintenanceKey = 'maintenance_mobile_flutter_android';
            }
        } elseif (str_contains($platformHeader, 'capacitor') || str_contains($userAgent, 'capacitor')) {
            $mobileCustomKey = 'mobile_capacitor';
            if (str_contains($platformHeader, 'ios') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad')) {
                $maintenanceKey = 'maintenance_mobile_capacitor_ios';
            } else {
                $maintenanceKey = 'maintenance_mobile_capacitor_android';
            }
        } else {
            // Deteksi runtime platform bawaan
            $runtime = AppPlatform::current($request);
            if ($runtime === RuntimePlatform::MobileAppAndroid) {
                $maintenanceKey = 'maintenance_mobile_capacitor_android';
                $mobileCustomKey = 'mobile_capacitor';
            } elseif ($runtime === RuntimePlatform::MobileAppIos) {
                $maintenanceKey = 'maintenance_mobile_capacitor_ios';
                $mobileCustomKey = 'mobile_capacitor';
            } elseif (str_contains($userAgent, 'android')) {
                $maintenanceKey = 'maintenance_mobile_flutter_android';
            } elseif (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad')) {
                $maintenanceKey = 'maintenance_mobile_flutter_ios';
            }
        }

        // Baca setting maintenance untuk platform yang ditargetkan
        $maintenanceSetting = FmAppSetting::where('key', $maintenanceKey)->first()
            ?? FmAppSetting::where('key', 'maintenance_user')->first()
            ?? FmAppSetting::where('key', 'maintenance_welcome')->first();

        $isMaintenance = false;
        $maintenanceMessage = '';

        if ($maintenanceSetting && is_array($maintenanceSetting->value)) {
            $isMaintenance = ! empty($maintenanceSetting->value['is_active']);
            $maintenanceMessage = $maintenanceSetting->value['message'] ?? '';
        }

        // Baca setting customizer mobile yang relevan
        $mobileSetting = FmAppSetting::where('key', $mobileCustomKey)->first();
        $mobile = ($mobileSetting && is_array($mobileSetting->value)) ? $mobileSetting->value : [];

        return response()->json([
            'status' => 'success',
            'data' => [
                'app_name' => $mobile['app_name'] ?? $appearance['brand_name'] ?? $config['app_name'] ?? config('app.name'),
                'owner_name' => $config['owner_name'] ?? config('app.name'),
                'demo_video_url' => $config['demo_video_url'] ?? '',
                'brand_name' => $appearance['brand_name'] ?? 'Dekorasi Bunga Pernikahan',
                'brand_logo' => $appearance['brand_logo'] ?? '/images/logo.png',
                'tagline' => $mobile['tagline'] ?? $appearance['tagline'] ?? 'Wujudkan Momen Pernikahan Impian Anda',
                'accent_color' => $mobile['primary_color'] ?? $appearance['accent_color'] ?? '#6366f1',
                'contact_whatsapp' => $appearance['contact_whatsapp'] ?? '+62 812-3456-7890',
                'contact_email' => $appearance['contact_email'] ?? 'support@weddingflowers.com',
                'splash_tagline' => $mobile['splash_tagline'] ?? 'Wujudkan Dekorasi Impian Anda',
                'play_store_url' => $mobile['play_store_url'] ?? null,
                'app_store_url' => $mobile['app_store_url'] ?? null,
                'min_app_version' => $mobile['min_app_version'] ?? '1.0.0',
                'force_update' => ! empty($mobile['force_update']),
                'maintenance' => [
                    'platform' => $maintenanceKey,
                    'is_active' => $isMaintenance,
                    'message' => $maintenanceMessage,
                ],
            ],
        ]);
    }
}
