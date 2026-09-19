<?php

namespace App\Http\Controllers\Api\User\AppSettingsController;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;

/**
 * App config untuk mobile (tanpa auth).
 * Semua teks/URL branding wajib dari backend Filament, bukan template di app.
 */
class AppSettingsController extends Controller
{
    public function index()
    {
        $config = Config::get('wedding_app', []);

        return response()->json([
            'status' => 'success',
            'data' => [
                'app_name' => $config['app_name'] ?? config('app.name'),
                'owner_name' => $config['owner_name'] ?? config('app.name'),
                'demo_video_url' => $config['demo_video_url'] ?? '',
            ],
        ]);
    }
}
