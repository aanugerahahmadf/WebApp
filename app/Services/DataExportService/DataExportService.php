<?php

namespace App\Services\DataExportService;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Admin\Exports\BankExporter\BankExporter;
use App\Filament\Admin\Exports\CartExporter\CartExporter;
use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Filament\Admin\Exports\DiscountExporter\DiscountExporter;
use App\Filament\Admin\Exports\HelpExporter\HelpExporter;
use App\Filament\Admin\Exports\OrderExporter\OrderExporter;
use App\Filament\Admin\Exports\PackageExporter\PackageExporter;
use App\Filament\Admin\Exports\PaymentGatewayExporter\PaymentGatewayExporter;
use App\Filament\Admin\Exports\PaymentMethodExporter\PaymentMethodExporter;
use App\Filament\Admin\Exports\PrivacyPolicyExporter\PrivacyPolicyExporter;
use App\Filament\Admin\Exports\ProductExporter\ProductExporter;
use App\Filament\Admin\Exports\ReferenceOptionExporter\ReferenceOptionExporter;
use App\Filament\Admin\Exports\ReportExporter\ReportExporter;
use App\Filament\Admin\Exports\ReviewExporter\ReviewExporter;
use App\Filament\Admin\Exports\TermsOfServiceExporter\TermsOfServiceExporter;
use App\Filament\Admin\Exports\TransactionExporter\TransactionExporter;
use App\Filament\Admin\Exports\UserExporter\UserExporter;
use App\Filament\Admin\Exports\VendorExporter\VendorExporter;
use App\Filament\Admin\Exports\VoucherExporter\VoucherExporter;
use App\Filament\Admin\Exports\WeddingDecorationPolicyExporter\WeddingDecorationPolicyExporter;
use App\Filament\Admin\Exports\WishlistExporter\WishlistExporter;
use App\Models\User\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Bus;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Orkestrator Pusat Unduhan Data.
 *
 * File XLSX selalu dihasilkan oleh Exporter bawaan Filament (kolom +
 * format resmi, job antrean yang sama seperti tombol "Ekspor Data" di tiap
 * tabel). Service ini hanya memilih dataset via toggle, menjalankan rantai
 * job native secara sinkron, lalu menggabungkan hasilnya ke satu ZIP.
 */
class DataExportService
{
    /**
     * @return array<string, array{label: string, filename: string, exporter: class-string}>
     */
    public static function datasets(): array
    {
        return [
            'users' => ['label' => __('Pengguna'), 'filename' => 'pengguna.xlsx', 'exporter' => UserExporter::class],
            'packages' => ['label' => __('Paket Dekorasi'), 'filename' => 'paket.xlsx', 'exporter' => PackageExporter::class],
            'products' => ['label' => __('Produk Bunga'), 'filename' => 'produk.xlsx', 'exporter' => ProductExporter::class],
            'categories' => ['label' => __('Kategori'), 'filename' => 'kategori.xlsx', 'exporter' => CategoryExporter::class],
            'orders' => ['label' => __('Pesanan'), 'filename' => 'pesanan.xlsx', 'exporter' => OrderExporter::class],
            'transactions' => ['label' => __('Transaksi'), 'filename' => 'transaksi.xlsx', 'exporter' => TransactionExporter::class],
            'reviews' => ['label' => __('Ulasan'), 'filename' => 'ulasan.xlsx', 'exporter' => ReviewExporter::class],
            'vouchers' => ['label' => __('Voucher'), 'filename' => 'voucher.xlsx', 'exporter' => VoucherExporter::class],
            'discounts' => ['label' => __('Diskon'), 'filename' => 'diskon.xlsx', 'exporter' => DiscountExporter::class],
            'wishlists' => ['label' => __('Favorit'), 'filename' => 'favorit.xlsx', 'exporter' => WishlistExporter::class],
            'carts' => ['label' => __('Keranjang'), 'filename' => 'keranjang.xlsx', 'exporter' => CartExporter::class],
            'banks' => ['label' => __('Bank'), 'filename' => 'bank.xlsx', 'exporter' => BankExporter::class],
            'payment_methods' => ['label' => __('Metode Pembayaran'), 'filename' => 'metode-pembayaran.xlsx', 'exporter' => PaymentMethodExporter::class],
            'payment_gateways' => ['label' => __('Gerbang Pembayaran'), 'filename' => 'gerbang-pembayaran.xlsx', 'exporter' => PaymentGatewayExporter::class],
            'vendors' => ['label' => __('Vendor / Toko'), 'filename' => 'vendor.xlsx', 'exporter' => VendorExporter::class],
            'reports' => ['label' => __('Laporan'), 'filename' => 'laporan.xlsx', 'exporter' => ReportExporter::class],
            'reference_options' => ['label' => __('Opsi Referensi'), 'filename' => 'opsi-referensi.xlsx', 'exporter' => ReferenceOptionExporter::class],
            'helps' => ['label' => __('Bantuan / FAQ'), 'filename' => 'bantuan.xlsx', 'exporter' => HelpExporter::class],
            'privacy_policies' => ['label' => __('Kebijakan Privasi'), 'filename' => 'kebijakan-privasi.xlsx', 'exporter' => PrivacyPolicyExporter::class],
            'terms_of_services' => ['label' => __('Ketentuan Layanan'), 'filename' => 'ketentuan-layanan.xlsx', 'exporter' => TermsOfServiceExporter::class],
            'wedding_policies' => ['label' => __('Kebijakan Dekorasi'), 'filename' => 'kebijakan-dekorasi.xlsx', 'exporter' => WeddingDecorationPolicyExporter::class],
        ];
    }

    /**
     * @return array<string, int> Jumlah baris per dataset.
     */
    public static function counts(): array
    {
        $counts = [];
        foreach (self::datasets() as $key => $dataset) {
            $counts[$key] = $dataset['exporter']::getModel()::query()->count();
        }

        return $counts;
    }

    /**
     * Jalankan SATU export native Filament (XLSX) persis seperti alur tombol
     * "Ekspor Data", minus notifikasi penyelesaiannya. Dengan antrean
     * sync, rantai berjalan seketika.
     */
    public static function runNativeExport(string $exporterClass, User $user): Export
    {
        $query = $exporterClass::getModel()::query();
        $query = $exporterClass::modifyQuery($query);

        $columnMap = collect($exporterClass::getColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();

        $totalRows = (clone $query)->toBase()->getCountForPagination();

        /** @var Export $export */
        $export = app(Export::class);
        $export->user()->associate($user);
        $export->exporter = $exporterClass;
        $export->total_rows = $totalRows;

        $exporter = $export->getExporter(columnMap: $columnMap, options: []);

        $export->file_disk = $exporter->getFileDisk();
        $export->save();

        // Hapus direktori arsip lama agar tidak tercampur.
        $export->deleteFileDirectory();

        $export->file_name = $exporter->getFileName($export);
        $export->save();

        $serializedQuery = EloquentSerializeFacade::serialize($query);

        $export->unsetRelation('user');

        Bus::chain([
            Bus::batch([app(PrepareCsvExport::class, [
                'export' => $export,
                'query' => $serializedQuery,
                'columnMap' => $columnMap,
                'options' => [],
                'chunkSize' => 100,
                'records' => null,
            ])])->allowFailures(),
            app(CreateXlsxFile::class, [
                'export' => $export,
                'columnMap' => $columnMap,
                'options' => [],
            ]),
        ])->dispatch();

        // Finalisasi senyap TANPA notifikasi per dataset (ExportCompletion
        // bawaan mengirim satu notifikasi per file = spam 21x dari halaman
        // gabungan; unduhan gabungan adalah umpan baliknya).
        $export->touch('completed_at');

        return $export->fresh();
    }

    /**
     * Jalankan export native untuk dataset terpilih lalu gabungkan semua
     * sheet-nya ke SATU file XLSX (satu sheet per dataset). Mengembalikan
     * path absolut file gabungan.
     *
     * @param  array<int, string>  $keys
     */
    public static function buildWorkbook(array $keys, User $user): string
    {
        $keys = array_values(array_intersect($keys, array_keys(self::datasets())));

        if ($keys === []) {
            throw new \InvalidArgumentException('Pilih minimal satu dataset.');
        }

        $directory = storage_path('app/private/data-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $master = new Spreadsheet;
        $master->removeSheetByIndex(0);

        foreach ($keys as $key) {
            $dataset = self::datasets()[$key];
            $export = self::runNativeExport($dataset['exporter'], $user);

            $disk = $export->getFileDisk();
            foreach ($disk->files($export->getFileDirectory()) as $file) {
                if (! str_ends_with(strtolower($file), '.xlsx')) {
                    continue;
                }

                // Pakai sheet aslinya (bukan clone): clone tetap terikat ke
                // workbook sumber sehingga getStyle() melempar "Sheet does
                // not exist" di workbook gabungan.
                $sourceBook = (new XlsxReader)->load($disk->path($file));
                $sheet = $sourceBook->getActiveSheet();
                $sheet->setTitle(mb_substr((string) preg_replace('/[*:\/\\\\?\[\]]/', '-', $dataset['label']), 0, 31));
                $master->addSheet($sheet);
                $sheet->rebindParent($master);
                self::humanizeSheet($sheet);
                self::styleSheet($sheet);
                break;
            }
        }

        if ($master->getSheetCount() === 0) {
            throw new \RuntimeException('Tidak ada sheet yang berhasil dibuat.');
        }

        $master->setActiveSheetIndex(0);

        $path = $directory.DIRECTORY_SEPARATOR.'data-aplikasi-'.date('Ymd-His').'.xlsx';
        (new XlsxWriter($master))->save($path);
        $master->disconnectWorksheets();

        return $path;
    }

    /**
     * Jalankan export native untuk dataset terpilih lalu gabungkan ke SATU
     * file PDF (satu bagian per dataset, tabel landscape). Mengembalikan
     * path absolut file PDF. Dipakai pilihan "PDF" di modal Unduh Data.
     *
     * @param  array<int, string>  $keys
     */
    public static function buildPdf(array $keys, User $user): string
    {
        $keys = array_values(array_intersect($keys, array_keys(self::datasets())));

        if ($keys === []) {
            throw new \InvalidArgumentException('Pilih minimal satu dataset.');
        }

        // PDF gabungan (khususnya Reviews ratusan baris) rakus memori & waktu.
        // Naikkan batas selama proses, kembalikan seperti semula setelahnya.
        $previousMemory = ini_get('memory_limit');
        $previousTimeLimit = ini_get('max_execution_time');
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        try {
            return self::buildPdfDocument($keys, $user);
        } finally {
            ini_set('memory_limit', $previousMemory);
            if ($previousTimeLimit !== false && $previousTimeLimit !== '') {
                set_time_limit((int) $previousTimeLimit);
            }
        }
    }

    /**
     * Isi sebenarnya buildPdf(), dipanggil dengan limit longgar.
     *
     * @param  array<int, string>  $keys
     */
    protected static function buildPdfDocument(array $keys, User $user): string
    {
        $keys = array_values(array_intersect($keys, array_keys(self::datasets())));

        if ($keys === []) {
            throw new \InvalidArgumentException('Pilih minimal satu dataset.');
        }

        $directory = storage_path('app/private/data-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $sections = '';

        foreach ($keys as $key) {
            $dataset = self::datasets()[$key];
            $export = self::runNativeExport($dataset['exporter'], $user);

            $rows = [];
            $disk = $export->getFileDisk();
            foreach ($disk->files($export->getFileDirectory()) as $file) {
                if (! str_ends_with(strtolower($file), '.xlsx')) {
                    continue;
                }

                $sheet = (new XlsxReader)->load($disk->path($file))->getActiveSheet();
                // Nilai terformat (tanggal tampil apa adanya), tanpa referensi sel.
                $rows = $sheet->toArray(null, true, true, false);
                break;
            }

            $title = (string) preg_replace('/[*:\/\\\\?\[\]]/', '-', $dataset['label']);
            $sections .= self::pdfSection($title, $rows);
        }

        if ($sections === '') {
            throw new \RuntimeException('Tidak ada data yang berhasil dibuat.');
        }

        $generatedAt = Carbon::now()->locale('id')->translatedFormat('d M Y H:i');
        $html = "<html><head><meta charset='utf-8'><style>"
            .'body{font-family:\'DejaVu Sans\',sans-serif;font-size:8px;color:#111}'
            .'h1{font-size:16px;margin:0 0 4px;text-align:center}.meta{font-size:9px;color:#555;margin-bottom:12px;text-align:center}'
            .'h2{font-size:12px;margin:10px 0 6px;color:#fff;background:#4472C4;font-weight:bold;text-align:center;padding:5px 4px}'
            .'table{width:100%;border-collapse:collapse;margin-bottom:8px}'
            .'th{background:#4472C4;color:#fff;font-weight:bold;padding:3px 4px;border:1px solid #909090;text-align:center}'
            .'td{padding:2px 4px;border:1px solid #B0B0B0;text-align:center;word-wrap:break-word}'
            .'.empty{color:#777;font-style:italic;margin-bottom:8px}'
            .'.page-break{page-break-after:always}'
            .'</style></head><body>'
            .'<h1>'.e(__('Unduhan Data Aplikasi')).'</h1>'
            .'<p class="meta">'.e(__('Dibuat pada :time', ['time' => $generatedAt])).'</p>'
            .$sections
            .'</body></html>';

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $path = $directory.DIRECTORY_SEPARATOR.'data-aplikasi-'.date('Ymd-His').'.pdf';
        file_put_contents($path, $dompdf->output());

        return $path;
    }

    /**
     * Satu bagian PDF: judul dataset + tabel datanya. Dataset besar dipecah
     * menjadi beberapa tabel (maksimal 200 baris per tabel, judul kolom
     * diulang) agar Dompdf tidak kehabisan memori.
     *
     * @param  array<int, array<int, mixed>>  $rows Baris pertama = judul kolom.
     */
    protected static function pdfSection(string $title, array $rows): string
    {
        $title = e($title === '' ? '-' : $title);

        if ($rows === [] || count($rows) < 1) {
            return "<h2>{$title}</h2><p class=\"empty\">".e(__('Tidak ada data.')).'</p><div class="page-break"></div>';
        }

        $header = array_shift($rows);

        $headHtml = '<thead><tr>';
        foreach ($header as $cell) {
            $headHtml .= '<th>'.e(self::pdfCell($cell)).'</th>';
        }
        $headHtml .= '</tr></thead>';

        $out = "<h2>{$title} (".number_format(count($rows)).' '.e(__('baris')).')</h2>';

        foreach (array_chunk($rows, 200) as $chunk) {
            $out .= '<table>'.$headHtml.'<tbody>';
            foreach ($chunk as $row) {
                $out .= '<tr>';
                foreach ($header as $index => $_) {
                    $out .= '<td>'.e(self::pdfCell($row[$index] ?? null)).'</td>';
                }
                $out .= '</tr>';
            }
            $out .= '</tbody></table>';
        }

        return $out.'<div class="page-break"></div>';
    }

    protected static function pdfCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? __('Ya') : __('Tidak');
        }

        if (is_string($value)) {
            $value = self::humanizeText($value);
        } else {
            $value = (string) $value;
        }

        // Potong sel yang sangat panjang agar PDF tidak membengkak / timeout.
        if (mb_strlen($value) > 150) {
            $value = mb_substr($value, 0, 150).'…';
        }

        return $value;
    }

    /**
     * Rapikan isi sel teks: tanpa underscore/strip — snake_case/slug jadi
     * Title Case berspasi, tanggal ISO jadi format Indonesia. URL, email,
     * path, JSON, UUID, dan angka dibiarkan apa adanya agar tidak rusak.
     */
    protected static function humanizeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();
        if ($lastRow < 2) {
            return;
        }

        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 2; $row <= $lastRow; $row++) {
            for ($col = 1; $col <= $lastCol; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = $cell->getValue();
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $clean = self::humanizeText($value);
                if ($clean !== $value) {
                    $cell->setValueExplicit($clean, DataType::TYPE_STRING);
                }
            }
        }
    }

    protected static function humanizeText(string $value): string
    {
        // Tanggal ISO → "02 Okt 2026 19:44".
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            try {
                $date = Carbon::parse($value)->locale('id');
                $out = $date->translatedFormat('d M Y');
                if (strlen($value) > 10) {
                    $out .= ' '.substr($value, 11, 5);
                }

                return $out;
            } catch (\Throwable $e) {
                return $value;
            }
        }

        // Lewati agar tidak rusak: email, URL/path ber-ekstensi, JSON,
        // tanggal-waktu/desimal (mengandung : atau .), UUID, teks panjang.
        if (str_contains($value, '@') || str_contains($value, ':') || str_contains($value, '.')
            || strlen($value) > 80
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            return $value;
        }

        // Label seperti snake_case, slug-strip, atau path label
        // (super_admin, paket-dekorasi, admin/super) → Title Case berspasi.
        // Tidak boleh ada sisa _, -, atau / di hasil akhir.
        if (preg_match('/^[A-Za-z][A-Za-z0-9 _\-\/]*$/', $value)
            && preg_match('/[_\/\-]/', $value)) {
            $words = preg_split('/[ _\-\/]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            $words = array_map(fn (string $word): string => ucfirst(strtolower($word)), $words ?: []);

            return implode(' ', $words);
        }

        return $value;
    }

    /**
     * Rapikan sheet ala tabel profesional: judul berwarna + tebal rata
     * tengah, garis di semua sel, isi rata tengah, bekukan baris judul,
     * filter otomatis, dan lebar kolom otomatis.
     */
    protected static function styleSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $lastRow = max(1, $sheet->getHighestRow());
        $lastColLetter = $sheet->getHighestColumn();
        $lastColIndex = Coordinate::columnIndexFromString($lastColLetter);
        $all = "A1:{$lastColLetter}{$lastRow}";
        $header = "A1:{$lastColLetter}1";

        $sheet->getStyle($all)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFB0B0B0'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle($header)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4472C4'],
            ],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter($all);

        foreach (range(1, $lastColIndex) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }
    }
}
