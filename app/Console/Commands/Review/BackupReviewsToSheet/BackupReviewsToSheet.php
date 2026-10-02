<?php

namespace App\Console\Commands\Review\BackupReviewsToSheet;

use App\Services\GoogleSheets\GoogleSheetsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ekspor data ulasan ke Google Sheet sebagai cadangan offsite.
 *
 * Yang ditulis ke sheet: baris review (rating, komentar, path foto, tanggal).
 * File fotonya sendiri TIDAK ikut -- Google Sheets hanya bisa menyimpan teks,
 * jadi yang dicatat di sheet adalah path relatif dan URL publiknya. Untuk
 * memindahkan file biner ke luar disk, pakai Google Drive, bukan Sheets.
 *
 *   php artisan reviews:backup-sheet
 *   php artisan reviews:backup-sheet --tab=ReviewsBackup
 *   php artisan reviews:backup-sheet --chunk=2000
 *
 * Konfigurasi (.env):
 *   GOOGLE_SHEETS_SPREADSHEET_ID=...
 *   GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON={"type":"service_account",...}
 *   GOOGLE_SHEETS_TAB=ReviewsBackup
 *
 * Sheet wajib dibagikan ke email service account dengan akses Editor.
 */
class BackupReviewsToSheet extends Command
{
    /**
     * Batas sel per request dari Google Sheets API.
     */
    protected const MAX_CELLS_PER_REQUEST = 2_000_000;

    protected $signature = 'reviews:backup-sheet
                            {--tab= : Nama tab tujuan (default dari GOOGLE_SHEETS_TAB)}
                            {--chunk=2000 : Baris per request}
                            {--dry-run : Tampilkan payload tanpa mengirim}';

    protected $description = 'Ekspor tabel reviews ke Google Sheet sebagai cadangan (teks saja, bukan file foto)';

    public function handle(): int
    {
        $client = $this->client();
        $tab = (string) ($this->option('tab') ?: config('services.google_sheets.tab', 'ReviewsBackup'));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        // Dry-run sengaja tidak butuh kredensial: gunanya untuk memeriksa
        // bentuk payload sebelum benar-benar mengirim ke Google.
        if (! $dryRun && ! $client->isConfigured()) {
            $this->components->error('Google Sheets belum dikonfigurasi.');
            $this->components->twoColumnDetail('GOOGLE_SHEETS_SPREADSHEET_ID', config('services.google_sheets.spreadsheet_id') ?: '(kosong)');
            $this->components->twoColumnDetail('GOOGLE_SHEETS_SERVICE_ACCOUNT_JSON', config('services.google_sheets.service_account_json') ? '(ada)' : '(kosong)');
            $this->newLine();
            $this->line('  Tambahkan keduanya ke .env, lalu bagikan sheet ke email service account dengan akses Editor.');
            $this->line('  Untuk memeriksa payload tanpa kredensial: --dry-run');

            return self::FAILURE;
        }

        $this->components->info($dryRun
            ? 'Dry-run: tidak ada data yang dikirim ke Google Sheets.'
            : 'Mengekspor reviews ke Google Sheets...');

        $this->components->twoColumnDetail('Spreadsheet', $this->spreadsheetId() ?: '(belum diset)');
        $this->components->twoColumnDetail('Tab', $tab);
        $this->components->twoColumnDetail('Baris per request', (string) $chunk);

        $baseUrl = rtrim((string) config('app.url'), '/');
        $header = [
            'review_id',
            'user_id',
            'user_name',
            'item_type',
            'item_id',
            'rating',
            'title',
            'comment',
            'photo_count',
            'photo_paths',
            'photo_urls',
            'helpful_count',
            'created_at',
        ];

        $rows = [$header];
        $written = 0;
        $chunkRows = [$header];
        $cellsInChunk = count($header);

        $reviews = DB::table('reviews')
            ->leftJoin('users', 'users.id', '=', 'reviews.user_id')
            ->select([
                'reviews.id',
                'reviews.user_id',
                'reviews.product_id',
                'reviews.package_id',
                'reviews.rating',
                'reviews.title',
                'reviews.comment',
                'reviews.photo',
                'reviews.photos',
                'reviews.helpful_count',
                'reviews.created_at',
                'users.full_name',
            ])
            ->orderBy('reviews.id')
            ->cursor();

        foreach ($reviews as $review) {
            $row = $this->mapRow($review, $baseUrl);
            $rows[] = $row;
            $chunkRows[] = $row;
            $cellsInChunk += count($row);

            if (count($chunkRows) >= $chunk || $cellsInChunk >= self::MAX_CELLS_PER_REQUEST) {
                $written += $this->flush($client, $tab, $chunkRows, $written, (bool) $this->option('dry-run'));
                $chunkRows = [];
                $cellsInChunk = 0;
            }
        }

        if ($chunkRows !== []) {
            $written += $this->flush($client, $tab, $chunkRows, $written, (bool) $this->option('dry-run'));
        }

        $this->newLine();
        $this->components->info('Selesai. Baris review diekspor: '.($written - 1));

        return self::SUCCESS;
    }

    /**
     * Tulis satu chunk ke sheet.
     *
     * Chunk ditulis ke baris paling awal lalu sisanya dibersihkan, supaya
     * menjalankan ulang tidak menumpuk data di bawahnya.
     */
    protected function flush(GoogleSheetsClient $client, string $tab, array $rows, int $alreadyWritten, bool $dryRun = false): int
    {
        $start = $alreadyWritten + 1;
        $range = sprintf('%s!A%d:%s%d', $this->quoteTab($tab), $start, $this->columnLetter(count($rows[0])), $start + count($rows) - 1);

        if ($dryRun) {
            $this->components->twoColumnDetail('Dry-run', $range.' ('.count($rows).' baris)');

            return count($rows);
        }

        $client->write($range, $rows);

        $this->components->twoColumnDetail('Terkirim', count($rows).' baris ke '.$range);

        return count($rows);
    }

    /**
     * Ubah satu baris DB menjadi baris sheet.
     *
     * @return array<int, string|int>
     */
    protected function mapRow(object $review, string $baseUrl): array
    {
        $paths = [];

        if (is_string($review->photo) && $review->photo !== '') {
            $paths[] = $review->photo;
        }

        if (is_string($review->photos)) {
            $decoded = json_decode($review->photos, true);

            if (is_array($decoded)) {
                foreach ($decoded as $path) {
                    if (is_string($path) && $path !== '') {
                        $paths[] = $path;
                    }
                }
            }
        }

        $paths = array_values(array_unique($paths));

        $urls = array_map(
            fn (string $p): string => $baseUrl.'/storage/'.$p,
            $paths,
        );

        return [
            (int) $review->id,
            $review->user_id === null ? '' : (int) $review->user_id,
            (string) ($review->full_name ?? ''),
            $review->product_id !== null ? 'product' : ($review->package_id !== null ? 'package' : ''),
            $review->product_id !== null ? (int) $review->product_id : ($review->package_id !== null ? (int) $review->package_id : ''),
            (int) $review->rating,
            (string) ($review->title ?? ''),
            (string) ($review->comment ?? ''),
            count($paths),
            implode(' | ', $paths),
            implode(' | ', $urls),
            (int) ($review->helpful_count ?? 0),
            (string) $review->created_at,
        ];
    }

    protected function client(): GoogleSheetsClient
    {
        return new GoogleSheetsClient(
            (string) config('services.google_sheets.spreadsheet_id'),
            (string) config('services.google_sheets.service_account_json'),
        );
    }

    protected function spreadsheetId(): string
    {
        return (string) config('services.google_sheets.spreadsheet_id');
    }

    /**
     * Nama tab bisa mengandung spasi, jadi harus dibungkus tanda kutip.
     */
    protected function quoteTab(string $tab): string
    {
        return "'".str_replace("'", "''", $tab)."'";
    }

    /**
     * 1 -> A, 2 -> B, ... 26 -> Z, 27 -> AA
     */
    protected function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}