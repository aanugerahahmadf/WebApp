<?php

namespace App\Http\Middleware\CheckWebsiteMaintenance;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\FmAppSetting\FmAppSetting;
use App\Support\AppPlatform\AppPlatform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckWebsiteMaintenance
{
    public function handle(Request $request, Closure $next, string $panel = 'welcome'): Response
    {
        // 1. Admin selalu diizinkan lewat
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // 2. Tentukan target key maintenance berdasarkan panel dan runtime Capacitor
        $targetKey = 'maintenance_' . $panel;
        $runtime = AppPlatform::current($request);

        if ($runtime === RuntimePlatform::MobileAppAndroid) {
            $capSetting = FmAppSetting::where('key', 'maintenance_mobile_capacitor_android')->first();
            if ($capSetting && is_array($capSetting->value) && ! empty($capSetting->value['is_active'])) {
                $targetKey = 'maintenance_mobile_capacitor_android';
            }
        } elseif ($runtime === RuntimePlatform::MobileAppIos) {
            $capSetting = FmAppSetting::where('key', 'maintenance_mobile_capacitor_ios')->first();
            if ($capSetting && is_array($capSetting->value) && ! empty($capSetting->value['is_active'])) {
                $targetKey = 'maintenance_mobile_capacitor_ios';
            }
        }

        // 3. Cek pengaturan status maintenance
        $setting = FmAppSetting::where('key', $targetKey)->first();
        if (! $setting || ! is_array($setting->value) || empty($setting->value['is_active'])) {
            return $next($request);
        }

        $config = $setting->value;

        // 4. Cek bypass secret (?bypass=...)
        $secret = $config['bypass_secret'] ?? '';
        if ($secret !== '' && $request->query('bypass') === $secret) {
            $request->session()->put('maintenance_bypass_' . $targetKey, true);
            return $next($request);
        }

        if ($request->session()->get('maintenance_bypass_' . $targetKey, false)) {
            return $next($request);
        }

        // 5. Cek whitelist IP
        $allowedIps = array_filter(array_map('trim', explode(',', (string) ($config['allowed_ips'] ?? ''))));
        if (! empty($allowedIps) && in_array($request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        // 6. Render halaman maintenance jika aktif
        $message = $config['message'] ?? __('Website sedang dalam pemeliharaan berkala.');
        $brandName = config('app-font.brand_name', 'Dekorasi Bunga Pernikahan');
        $httpStatus = (int) ($config['http_status'] ?? 503);

        return response()->view('Shared.maintenance.maintenance', [
            'panel' => $panel,
            'message' => $message,
            'brandName' => $brandName,
        ], $httpStatus);
    }
}
