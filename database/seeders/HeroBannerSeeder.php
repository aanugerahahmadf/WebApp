<?php

namespace Database\Seeders;

use App\Models\Home\HeroBanner;
use Illuminate\Database\Seeder;

class HeroBannerSeeder extends Seeder
{
    public function run(): void
    {
        // Demo content is inserted only for an empty table. Re-running the
        // normal seeder must never overwrite banners managed by an admin.
        if (HeroBanner::query()->exists()) {
            $this->command?->info('Banner promosi sudah tersedia; tidak ditimpa.');

            return;
        }

        HeroBanner::factory()
            ->count(3)
            ->sequence(fn ($sequence): array => ['position' => $sequence->index + 1])
            ->create();

        $this->command?->info('3 banner promosi demo berhasil dibuat.');
    }
}
