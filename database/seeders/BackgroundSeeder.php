<?php

namespace Database\Seeders;

use App\Models\FmAppSetting\FmAppSetting;
use Illuminate\Database\Seeder;

class BackgroundSeeder extends Seeder
{
    public function run(): void
    {
        FmAppSetting::updateOrCreate(
            ['key' => 'background'],
            ['value' => [
                'active' => true,
                'type' => 'image',
                'image' => '/images/background/background-4k.jpg',
                'video' => null,
                'overlay' => '#000000',
                'overlay_opacity' => 20,
                'blur' => 2,
                'parallax_strength' => 18,
                'parallax_speed' => 0.12,
            ]]
        );
    }
}
