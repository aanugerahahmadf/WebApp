<?php

namespace App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ViewDeveloperStudio;

use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeveloperStudio extends \Filament\Resources\Pages\ViewRecord
{

    protected static string $resource = DeveloperStudioResource::class;

    /**
     * View sendiri (bukan form generik).
     *
     * Halaman ini bukan sekadar "lihat baris": endpoint punya contoh
     * pemanggilan (cURL/Flutter/Capacitor/Python), isi berkas, ukuran berkas,
     * dan middleware. Semuanya sudah tersedia sebagai method di model
     * (`getCurlCommand()`, `getFlutterSnippet()`, dst.) -- view generik tidak
     * pernah memakainya, jadi informasi itu tidak pernah terlihat.
     */
    protected static string $view = 'Admin.resources.developer-studio.pages.view-developer-studio';

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
