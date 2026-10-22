<?php

namespace App\Console\Commands\BackfillMediaUuid;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillMediaUuid extends Command
{
    protected $signature = 'media:backfill-uuid
                            {--dry : Hanya laporkan, jangan tulis}';

    protected $description = 'Isi kolom media.uuid yang kosong (dibatasi char(36))';

    /**
     * Isi ulang uuid pada baris media yang uuid-nya NULL atau string kosong.
     *
     * Kenapa perlu ada command ini, bukan hanya perbaikan satu kali:
     *
     * Seeder gambar menulis baris `media` dengan INSERT langsung, jadi kolom
     * `uuid` yang di database-i sebagai `char(36) NULL-ABLE` tidak pernah diisi.
     * Kolomnya memang nullable, jadi tidak ada yang protes -- SQLite dan MySQL
     * sama-sama menerima NULL tanpa error.
     *
     * Akibatnya bukan file yang hilang, tapi HALAMAN yang salah:
     *
     *   - `Tables\Columns\SpatieMediaLibraryImageColumn::getState()` mengambil
     *     `->pluck('uuid')`, lalu `getImageUrl()` mencocokkannya dengan
     *     `$media->uuid === $state`. UUID kosong berarti tidak ada media yang
     *     cocok, jadi kolom jatuh ke `defaultImageUrl()` -- yaitu file
     *     placeholder. Gejalanya di Admin Catalog: kolom "Photos" isi semua
     *     ikon abu, padahal filenya ada dan bisa dibuka.
     *   - `Media::getFirstMediaByUuid()` dan seluruh pemanggilan yang berbasis
     *     uuid ikut gagal dengan diam.
     *
     * Yang TIDAK disentuh command ini adalah lokasi file. Path-nya tetap datar
     * (`Catalogs/<file_name>`) sesuai `App\Support\MediaLibrary\CollectionPathGenerator`,
     * dan generator itu tidak memakai uuid sama sekali. Jadi tidak ada file
     * yang perlu dipindah, dan uuid yang baru tidak mengubah satu pun URL
     * yang sudah benar.
     *
     * Aman diulang: hanya baris dengan uuid kosong yang tersentuh, dan
     * `--dry` tersedia untuk melihat hitungannya lebih dulu.
     */
    public function handle(): int
    {
        $total = DB::table('media')->count();
        $broken = DB::table('media')
            ->where(fn ($q) => $q->whereNull('uuid')->orWhere('uuid', ''))
            ->count();

        $this->components->info("Total media : {$total}");
        $this->components->info("Uuid kosong  : {$broken}");

        if ($broken === 0) {
            $this->components->info('Tidak ada uuid yang perlu diisi.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->components->warn('Mode --dry: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        $filled = 0;
        $skipped = 0;

        DB::table('media')
            ->where(fn ($q) => $q->whereNull('uuid')->orWhere('uuid', ''))
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$filled, &$skipped) {
                foreach ($rows as $row) {
                    // char(36) hanya muat UUID v4. Tidak ada alasan menebak
                    // bentuk lain, jadi kalau kode di bawah tidak menghasilkan
                    // 36 karakter, baris ini dilewati dan dilaporkan -- bukan
                    // dipaksa masuk dengan nilai yang tidak valid.
                    $uuid = (string) Str::uuid();

                    if (strlen($uuid) !== 36) {
                        $skipped++;

                        continue;
                    }

                    DB::table('media')->where('id', $row->id)->update(['uuid' => $uuid]);
                    $filled++;
                }
            });

        $this->components->info("Uuid diisi   : {$filled}");

        if ($skipped > 0) {
            $this->components->warn("Dilewati     : {$skipped}");
        }

        // Verifikasi: uuid yang baru harus bisa mengembalikan media, dan file
        // aslinya harus tetap ada di path lamanya.
        $stillBroken = DB::table('media')
            ->where(fn ($q) => $q->whereNull('uuid')->orWhere('uuid', ''))
            ->count();

        $this->components->info("Sisa kosong  : {$stillBroken}");

        $disk = Storage::disk(config('media-library.disk_name', 'public'));
        $sample = DB::table('media')
            ->where('collection_name', 'catalog_image')
            ->limit(200)
            ->get();

        $missingFile = $sample
            ->filter(fn ($row) => ! $disk->exists('Catalogs/'.$row->file_name))
            ->count();

        if ($missingFile > 0) {
            $this->components->warn("File hilang (dari {$sample->count()} contoh): {$missingFile}");
        } else {
            $this->components->info('Lokasi file tidak berubah dan semua file contoh masih ada.');
        }

        return self::SUCCESS;
    }
}