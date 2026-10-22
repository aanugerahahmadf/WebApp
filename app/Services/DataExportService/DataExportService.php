<?php

namespace App\Services\DataExportService;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Admin\Exports\ActivityLogExporter\ActivityLogExporter;
use App\Filament\Admin\Exports\AdminExporter\AdminExporter;
use App\Filament\Admin\Exports\BackupCodeExporter\BackupCodeExporter;
use App\Filament\Admin\Exports\BankExporter\BankExporter;
use App\Filament\Admin\Exports\CartExporter\CartExporter;
use App\Filament\Admin\Exports\CatalogExporter\CatalogExporter;
use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Filament\Admin\Exports\CbirFeedbackExporter\CbirFeedbackExporter;
use App\Filament\Admin\Exports\CbirSearchExporter\CbirSearchExporter;
use App\Filament\Admin\Exports\DeveloperStudioEndpointExporter\DeveloperStudioEndpointExporter;
use App\Filament\Admin\Exports\DeviceTokenExporter\DeviceTokenExporter;
use App\Filament\Admin\Exports\DiscountExporter\DiscountExporter;
use App\Filament\Admin\Exports\ExportLogExporter\ExportLogExporter;
use App\Filament\Admin\Exports\FmAppSettingExporter\FmAppSettingExporter;
use App\Filament\Admin\Exports\HelpExporter\HelpExporter;
use App\Filament\Admin\Exports\HeroBannerExporter\HeroBannerExporter;
use App\Filament\Admin\Exports\ImportLogExporter\ImportLogExporter;
use App\Filament\Admin\Exports\InboxExporter\InboxExporter;
use App\Filament\Admin\Exports\LegalPageExporter\LegalPageExporter;
use App\Filament\Admin\Exports\MediaExporter\MediaExporter;
use App\Filament\Admin\Exports\MessageExporter\MessageExporter;
use App\Filament\Admin\Exports\NotificationExporter\NotificationExporter;
use App\Filament\Admin\Exports\OrderExporter\OrderExporter;
use App\Filament\Admin\Exports\OrderPaymentExporter\OrderPaymentExporter;
use App\Filament\Admin\Exports\OrderTimelineExporter\OrderTimelineExporter;
use App\Filament\Admin\Exports\PackageExporter\PackageExporter;
use App\Filament\Admin\Exports\PaymentGatewayExporter\PaymentGatewayExporter;
use App\Filament\Admin\Exports\PaymentMethodExporter\PaymentMethodExporter;
use App\Filament\Admin\Exports\PermissionExporter\PermissionExporter;
use App\Filament\Admin\Exports\PrivacyPolicyExporter\PrivacyPolicyExporter;
use App\Filament\Admin\Exports\PromotionExporter\PromotionExporter;
use App\Filament\Admin\Exports\ReferenceOptionExporter\ReferenceOptionExporter;
use App\Filament\Admin\Exports\RefundRequestExporter\RefundRequestExporter;
use App\Filament\Admin\Exports\ReportExporter\ReportExporter;
use App\Filament\Admin\Exports\ReviewExporter\ReviewExporter;
use App\Filament\Admin\Exports\ReviewReplyExporter\ReviewReplyExporter;
use App\Filament\Admin\Exports\ReviewReportExporter\ReviewReportExporter;
use App\Filament\Admin\Exports\ReviewVoteExporter\ReviewVoteExporter;
use App\Filament\Admin\Exports\RoleExporter\RoleExporter;
use App\Filament\Admin\Exports\SearchLogExporter\SearchLogExporter;
use App\Filament\Admin\Exports\SecurityEmailExporter\SecurityEmailExporter;
use App\Filament\Admin\Exports\TermsOfServiceExporter\TermsOfServiceExporter;
use App\Filament\Admin\Exports\TransactionExporter\TransactionExporter;
use App\Filament\Admin\Exports\TranslationExporter\TranslationExporter;
use App\Filament\Admin\Exports\TrustedDeviceExporter\TrustedDeviceExporter;
use App\Filament\Admin\Exports\UserAddressExporter\UserAddressExporter;
use App\Filament\Admin\Exports\UserExporter\UserExporter;
use App\Filament\Admin\Exports\UserLanguageExporter\UserLanguageExporter;
use App\Filament\Admin\Exports\UserSessionExporter\UserSessionExporter;
use App\Filament\Admin\Exports\UserVoucherExporter\UserVoucherExporter;
use App\Filament\Admin\Exports\VoucherExporter\VoucherExporter;
use App\Filament\Admin\Exports\WeddingDecorationPolicyExporter\WeddingDecorationPolicyExporter;
use App\Filament\Admin\Exports\WhatsappOtpExporter\WhatsappOtpExporter;
use App\Filament\Admin\Exports\WishlistExporter\WishlistExporter;
use App\Support\ExportText\ExportText;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;
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
     * Kelompok dataset, berurutan. Dipakai untuk membagi toggle di panel
     * admin jadi beberapa bagian -- 55 toggle dalam satu daftar flat
     * impossible dibaca.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_IDENTITY,
            self::GROUP_CATALOG,
            self::GROUP_ORDER,
            self::GROUP_REVIEW,
            self::GROUP_PAYMENT,
            self::GROUP_CHAT,
            self::GROUP_SEARCH,
            self::GROUP_CONTENT,
            self::GROUP_SYSTEM,
        ];
    }

    public const GROUP_IDENTITY = 'Pengguna & Tim';
    public const GROUP_CATALOG = 'Katalog & Paket';
    public const GROUP_ORDER = 'Pesanan & Pembayaran';
    public const GROUP_REVIEW = 'Ulasan & Laporan';
    public const GROUP_PAYMENT = 'Voucher & Metode Bayar';
    public const GROUP_CHAT = 'Chat';
    public const GROUP_SEARCH = 'Pencarian & AI';
    public const GROUP_CONTENT = 'Konten & Legal';
    public const GROUP_SYSTEM = 'Sistem & Audit';

    /**
     * SEMUA dataset yang bisa diunduh dari Pusat Unduhan Data.
     *
     * Sifat daftar ini: LENGKAP. Satu baris untuk SETIAP tabel yang
     * menyimpan data aplikasi -- dari `users` sampai `user_vouchers` (tabel
     * pivot), `media`, `notifications`, sampai log aktivitas.
     *
     * Kenapa tidak ada sisa: Pusat Unduhan Data dipakai untuk analisis data
     * (skripsi), jadi "yang tidak ada di sini" berarti "tidak bisa dianalisis".
     * Tabel yang SENGAJA tidak dimasukkan hanya yang isinya bukan data
     * aplikasi: cache, job, job batch, tabel `migrations`, dan tabel token
     * (`personal_access_tokens`, `admin_password_reset_tokens`) -- yang terakhir
     * berisi rahasia, tidak boleh bisa diunduh.
     *
     * Urutan daftar = urutan sheet di XLSX dan urutan bagian di PDF, jadi
     * dikelompokkan logis: pengguna dulu, lalu katalog, pesanan, ulasan,
     * konten, terakhir data sistem.
     *
     * @return array<string, array{label: string, filename: string, exporter: class-string, group: string}>
     */
    public static function datasets(): array
    {
        return [
            // ── Pengguna & tim ───────────────────────────────────────────
            'users' => ['label' => __('Pengguna'), 'filename' => 'pengguna.xlsx', 'exporter' => UserExporter::class, 'group' => self::GROUP_IDENTITY],
            'admins' => ['label' => __('Tim Admin'), 'filename' => 'tim-admin.xlsx', 'exporter' => AdminExporter::class, 'group' => self::GROUP_IDENTITY],
            'user_addresses' => ['label' => __('Alamat Pengguna'), 'filename' => 'alamat-pengguna.xlsx', 'exporter' => UserAddressExporter::class, 'group' => self::GROUP_IDENTITY],
            'user_languages' => ['label' => __('Bahasa Pengguna'), 'filename' => 'bahasa-pengguna.xlsx', 'exporter' => UserLanguageExporter::class, 'group' => self::GROUP_IDENTITY],
            'user_sessions' => ['label' => __('Sesi Pengguna'), 'filename' => 'sesi-pengguna.xlsx', 'exporter' => UserSessionExporter::class, 'group' => self::GROUP_IDENTITY],
            'device_tokens' => ['label' => __('Token Perangkat'), 'filename' => 'token-perangkat.xlsx', 'exporter' => DeviceTokenExporter::class, 'group' => self::GROUP_IDENTITY],
            'trusted_devices' => ['label' => __('Perangkat Tepercaya'), 'filename' => 'perangkat-dipercayai.xlsx', 'exporter' => TrustedDeviceExporter::class, 'group' => self::GROUP_IDENTITY],
            'backup_codes' => ['label' => __('Kode Cadangan'), 'filename' => 'kode-cadangan.xlsx', 'exporter' => BackupCodeExporter::class, 'group' => self::GROUP_IDENTITY],
            'whatsapp_otps' => ['label' => __('OTP WhatsApp'), 'filename' => 'otp-whatsapp.xlsx', 'exporter' => WhatsappOtpExporter::class, 'group' => self::GROUP_IDENTITY],
            'security_emails' => ['label' => __('Email Keamanan'), 'filename' => 'email-keamanan.xlsx', 'exporter' => SecurityEmailExporter::class, 'group' => self::GROUP_IDENTITY],

            // ── Katalog & paket ───────────────────────────────────────────
            'categories' => ['label' => __('Kategori'), 'filename' => 'kategori.xlsx', 'exporter' => CategoryExporter::class, 'group' => self::GROUP_CATALOG],
            'packages' => ['label' => __('Paket Dekorasi'), 'filename' => 'paket.xlsx', 'exporter' => PackageExporter::class, 'group' => self::GROUP_CATALOG],
            'catalogs' => ['label' => __('Katalog Bunga'), 'filename' => 'katalog.xlsx', 'exporter' => CatalogExporter::class, 'group' => self::GROUP_CATALOG],
            'media' => ['label' => __('Pustaka Media'), 'filename' => 'pustaka-media.xlsx', 'exporter' => MediaExporter::class, 'group' => self::GROUP_CATALOG],

            // ── Pesanan & pembayaran ──────────────────────────────────────
            'orders' => ['label' => __('Pesanan'), 'filename' => 'pesanan.xlsx', 'exporter' => OrderExporter::class, 'group' => self::GROUP_ORDER],
            'order_payments' => ['label' => __('Pembayaran Pesanan'), 'filename' => 'pembayaran-pesanan.xlsx', 'exporter' => OrderPaymentExporter::class, 'group' => self::GROUP_ORDER],
            'order_timelines' => ['label' => __('Riwayat Status Pesanan'), 'filename' => 'riwayat-status-pesanan.xlsx', 'exporter' => OrderTimelineExporter::class, 'group' => self::GROUP_ORDER],
            'refund_requests' => ['label' => __('Permintaan Refund'), 'filename' => 'permintaan-refund.xlsx', 'exporter' => RefundRequestExporter::class, 'group' => self::GROUP_ORDER],
            'transactions' => ['label' => __('Transaksi'), 'filename' => 'transaksi.xlsx', 'exporter' => TransactionExporter::class, 'group' => self::GROUP_ORDER],
            'carts' => ['label' => __('Keranjang'), 'filename' => 'keranjang.xlsx', 'exporter' => CartExporter::class, 'group' => self::GROUP_ORDER],
            'wishlists' => ['label' => __('Favorit'), 'filename' => 'favorit.xlsx', 'exporter' => WishlistExporter::class, 'group' => self::GROUP_ORDER],

            // ── Ulasan & laporan ──────────────────────────────────────────
            'reviews' => ['label' => __('Ulasan'), 'filename' => 'ulasan.xlsx', 'exporter' => ReviewExporter::class, 'group' => self::GROUP_REVIEW],
            'review_replies' => ['label' => __('Balasan Ulasan'), 'filename' => 'balasan-ulasan.xlsx', 'exporter' => ReviewReplyExporter::class, 'group' => self::GROUP_REVIEW],
            'review_votes' => ['label' => __('Suara Ulasan'), 'filename' => 'suara-ulasan.xlsx', 'exporter' => ReviewVoteExporter::class, 'group' => self::GROUP_REVIEW],
            'review_reports' => ['label' => __('Laporan Ulasan'), 'filename' => 'laporan-ulasan.xlsx', 'exporter' => ReviewReportExporter::class, 'group' => self::GROUP_REVIEW],
            'reports' => ['label' => __('Laporan'), 'filename' => 'laporan.xlsx', 'exporter' => ReportExporter::class, 'group' => self::GROUP_REVIEW],

            // ── Voucher & metode bayar ────────────────────────────────────
            'vouchers' => ['label' => __('Voucher'), 'filename' => 'voucher.xlsx', 'exporter' => VoucherExporter::class, 'group' => self::GROUP_PAYMENT],
            'user_vouchers' => ['label' => __('Voucher Pengguna'), 'filename' => 'voucher-pengguna.xlsx', 'exporter' => UserVoucherExporter::class, 'group' => self::GROUP_PAYMENT],
            'discounts' => ['label' => __('Diskon'), 'filename' => 'diskon.xlsx', 'exporter' => DiscountExporter::class, 'group' => self::GROUP_PAYMENT],
            'banks' => ['label' => __('Bank'), 'filename' => 'bank.xlsx', 'exporter' => BankExporter::class, 'group' => self::GROUP_PAYMENT],
            'payment_methods' => ['label' => __('Metode Pembayaran'), 'filename' => 'metode-pembayaran.xlsx', 'exporter' => PaymentMethodExporter::class, 'group' => self::GROUP_PAYMENT],
            'payment_gateways' => ['label' => __('Gerbang Pembayaran'), 'filename' => 'gerbang-pembayaran.xlsx', 'exporter' => PaymentGatewayExporter::class, 'group' => self::GROUP_PAYMENT],

            // ── Chat ──────────────────────────────────────────────────────
            'inboxes' => ['label' => __('Kotak Masuk'), 'filename' => 'kotak-masuk.xlsx', 'exporter' => InboxExporter::class, 'group' => self::GROUP_CHAT],
            'messages' => ['label' => __('Pesan'), 'filename' => 'pesan.xlsx', 'exporter' => MessageExporter::class, 'group' => self::GROUP_CHAT],

            // ── Pencarian & AI (CBIR) ─────────────────────────────────────
            'search_logs' => ['label' => __('Riwayat Pencarian'), 'filename' => 'riwayat-pencarian.xlsx', 'exporter' => SearchLogExporter::class, 'group' => self::GROUP_SEARCH],
            'cbir_searches' => ['label' => __('Pencarian CBIR'), 'filename' => 'pencarian-cbir.xlsx', 'exporter' => CbirSearchExporter::class, 'group' => self::GROUP_SEARCH],
            'cbir_feedback' => ['label' => __('Masukan CBIR'), 'filename' => 'masukan-cbir.xlsx', 'exporter' => CbirFeedbackExporter::class, 'group' => self::GROUP_SEARCH],

            // ── Konten, legal & referensi ─────────────────────────────────
            'helps' => ['label' => __('Bantuan dan FAQ'), 'filename' => 'bantuan.xlsx', 'exporter' => HelpExporter::class, 'group' => self::GROUP_CONTENT],
            'legal_pages' => ['label' => __('Halaman Legal'), 'filename' => 'halaman-legal.xlsx', 'exporter' => LegalPageExporter::class, 'group' => self::GROUP_CONTENT],
            'privacy_policies' => ['label' => __('Kebijakan Privasi'), 'filename' => 'kebijakan-privasi.xlsx', 'exporter' => PrivacyPolicyExporter::class, 'group' => self::GROUP_CONTENT],
            'terms_of_services' => ['label' => __('Ketentuan Layanan'), 'filename' => 'ketentuan-layanan.xlsx', 'exporter' => TermsOfServiceExporter::class, 'group' => self::GROUP_CONTENT],
            'wedding_policies' => ['label' => __('Kebijakan Dekorasi'), 'filename' => 'kebijakan-dekorasi.xlsx', 'exporter' => WeddingDecorationPolicyExporter::class, 'group' => self::GROUP_CONTENT],
            'reference_options' => ['label' => __('Opsi Referensi'), 'filename' => 'opsi-referensi.xlsx', 'exporter' => ReferenceOptionExporter::class, 'group' => self::GROUP_CONTENT],
            'translations' => ['label' => __('Terjemahan'), 'filename' => 'terjemahan.xlsx', 'exporter' => TranslationExporter::class, 'group' => self::GROUP_CONTENT],
            'hero_banners' => ['label' => __('Banner Utama'), 'filename' => 'banner-utama.xlsx', 'exporter' => HeroBannerExporter::class, 'group' => self::GROUP_CONTENT],
            'promotions' => ['label' => __('Promosi'), 'filename' => 'promosi.xlsx', 'exporter' => PromotionExporter::class, 'group' => self::GROUP_CONTENT],

            // ── Data sistem & audit ───────────────────────────────────────
            'fm_app_settings' => ['label' => __('Pengaturan Aplikasi'), 'filename' => 'pengaturan-aplikasi.xlsx', 'exporter' => FmAppSettingExporter::class, 'group' => self::GROUP_SYSTEM],
            'roles' => ['label' => __('Peran Akses'), 'filename' => 'peran-akses.xlsx', 'exporter' => RoleExporter::class, 'group' => self::GROUP_SYSTEM],
            'permissions' => ['label' => __('Izin Akses'), 'filename' => 'izin-akses.xlsx', 'exporter' => PermissionExporter::class, 'group' => self::GROUP_SYSTEM],
            'activity_log' => ['label' => __('Log Aktivitas'), 'filename' => 'log-aktivitas.xlsx', 'exporter' => ActivityLogExporter::class, 'group' => self::GROUP_SYSTEM],
            'notifications' => ['label' => __('Notifikasi'), 'filename' => 'notifikasi.xlsx', 'exporter' => NotificationExporter::class, 'group' => self::GROUP_SYSTEM],
            'developer_studio_endpoints' => ['label' => __('Endpoint Developer Studio'), 'filename' => 'endpoint-developer-studio.xlsx', 'exporter' => DeveloperStudioEndpointExporter::class, 'group' => self::GROUP_SYSTEM],
            'export_logs' => ['label' => __('Riwayat Unduhan'), 'filename' => 'riwayat-unduhan.xlsx', 'exporter' => ExportLogExporter::class, 'group' => self::GROUP_SYSTEM],
            'import_logs' => ['label' => __('Riwayat Impor'), 'filename' => 'riwayat-impor.xlsx', 'exporter' => ImportLogExporter::class, 'group' => self::GROUP_SYSTEM],
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
     *
     * `$actor` adalah SIAPA yang menjemduh: bisa `Admin` (panel admin,
     * guard `admin`) atau `User` (panel storefront, guard `web`). Keduanya
     * dicatat apa adanya lewat relasi `exports.user` yang polimorfik.
     */
    public static function runNativeExport(string $exporterClass, Authenticatable $actor): Export
    {
        $query = $exporterClass::getModel()::query();
        $query = $exporterClass::modifyQuery($query);

        $columnMap = collect($exporterClass::getColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();

        $totalRows = (clone $query)->toBase()->getCountForPagination();

        /** @var Export $export */
        $export = app(Export::class);
        $export->user()->associate($actor);
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
    public static function buildWorkbook(array $keys, Authenticatable $actor): string
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
            $export = self::runNativeExport($dataset['exporter'], $actor);

            $disk = $export->getFileDisk();
            foreach ($disk->files($export->getFileDirectory()) as $file) {
                if (! str_ends_with(strtolower($file), '.xlsx')) {
                    continue;
                }

                // Pakai sheet aslinya (bukan clone): clone tetap terikat ke
                // workbook sumber sehingga getStyle() melempar "Sheet does
                // not exist" di workbook gabungan.
                //
                // `setReadDataOnly(true)` WAJIB: tanpa itu pembaca XLSX
                // PhpSpreadsheet mengembalikan objek `RichText` untuk setiap
                // sel string -- sehingga nilai sel tidak pernah berupa
                // string, dan pembersihan teks (humanizeSheet) DILEWATI
                // tanpa error sama sekali.
                $sourceBook = (new XlsxReader)->setReadDataOnly(true)->load($disk->path($file));
                $sheet = $sourceBook->getActiveSheet();
                $sheet->setTitle(self::sheetTitle($dataset['label']));
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
    public static function buildPdf(array $keys, Authenticatable $actor): string
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
            return self::buildPdfDocument($keys, $actor);
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
    protected static function buildPdfDocument(array $keys, Authenticatable $actor): string
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
            $export = self::runNativeExport($dataset['exporter'], $actor);

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
            $sections .= self::pdfSection(ExportText::clean($title, '-'), $rows);
        }

        if ($sections === '') {
            throw new \RuntimeException('Tidak ada data yang berhasil dibuat.');
        }

        $generatedAt = Carbon::now()->locale('id')->translatedFormat('d M Y H:i');
        $html = "<html><head><meta charset='utf-8'><style>"
            .\App\Support\PdfLayout\PdfLayout::pageCss('landscape', self::pdfCss())
            .'</style></head><body>'
            .\App\Support\PdfLayout\PdfLayout::header(__('Unduhan Data Aplikasi'))
            .\App\Support\PdfLayout\PdfLayout::footer()
            .'<h1>'.e(__('Unduhan Data Aplikasi')).'</h1>'
            .'<p class="meta">'.e(__('Dibuat pada :time', ['time' => $generatedAt])).'</p>'
            .$sections
            .'</body></html>';

        $dompdf = \App\Support\PdfLayout\PdfLayout::apply(new Dompdf);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $path = $directory.DIRECTORY_SEPARATOR.'data-aplikasi-'.date('Ymd-His').'.pdf';
        file_put_contents($path, $dompdf->output());

        return $path;
    }

    /**
     * CSS tabel PDF.
     *
     * Tiga hal yang dijaga di sini:
     *
     * 1. TABEL MENGISI LEBAR KERTAS ("rata kiri-kanan"). `width:100%` +
     *    `table-layout:fixed` + `<colgroup>` berisi lebar per kolom membuat
     *    tabel persis membentang dari margin kiri ke margin kanan. Tanpa
     *    `table-layout:fixed`, Dompdf menghitung lebar otomatis dan tabel
     *    bisa menempel di kiri saja -- sisanya ruang kosong.
     *
     * 2. TEKS TIDAK "CRUSH". `word-wrap:break-word` + `white-space:normal`
     *    membuat teks panjang membungkus di dalam sel, bukan memaksa kolom
     *    melebar atau memotong isi. `font-size` ikut dikecilkan kalau kolomnya
     *    banyak, supaya tabel tetap muat di lebar kertas.
     *
     * 3. BARIS TIDAK TERPISAH HALAMAN. `page-break-inside:avoid` pada `tr`
     *    menjaga isi satu baris tetap utuh di satu halaman.
     */
    protected static function pdfCss(): string
    {
        return 'body{font-size:8px;color:#111;line-height:1.25}'
            .'h1{font-size:16px;margin:0 0 4px;text-align:center}'
            .'.meta{font-size:9px;color:#555;margin-bottom:14px;text-align:center}'
            .'h2{font-size:11px;margin:0 0 5px;color:#fff;background:#4472C4;'
            .'font-weight:bold;text-align:center;padding:5px 4px;border-radius:2px}'
            .'.section-meta{font-size:8px;color:#555;margin:0 0 4px;text-align:center}'
            .'table{width:100%;border-collapse:collapse;margin:0 0 6px;table-layout:fixed}'
            .'th{background:#4472C4;color:#fff;font-weight:bold;padding:3px 4px;'
            .'border:1px solid #7f7f7f;text-align:center;vertical-align:middle;word-wrap:break-word}'
            .'td{padding:2px 4px;border:1px solid #B0B0B0;text-align:left;'
            .'vertical-align:top;word-wrap:break-word;white-space:normal}'
            .'tbody tr:nth-child(even) td{background:#F4F7FB}'
            .'tr{page-break-inside:avoid}'
            .'thead{display:table-header-group}'
            .'tbody{display:table-row-group}'
            .'.empty{color:#777;font-style:italic;margin-bottom:8px;text-align:center}'
            .'.page-break{page-break-after:always}';
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

        // Lebar kolom dihitung dari isi TERPANJANG tiap kolom, lalu
        // dinormalisasi jadi persen yang totalnya 100. Ini yang membuat
        // tabel membentang penuh dari margin kiri ke margin kanan, dan
        // kolom yang isinya panjang (deskripsi, alamat) tetap dapat ruang
        // yang layak -- bukan dipipihkan jadi satu karakter.
        $widths = self::pdfColumnWidths($header, $rows);

        $headHtml = '<thead><tr>';
        foreach ($header as $index => $cell) {
            $headHtml .= '<th>'.e(ExportText::heading((string) $cell)).'</th>';
        }
        $headHtml .= '</tr></thead>';

        $colGroup = '<colgroup>';
        foreach ($widths as $width) {
            $colGroup .= '<col style="width:'.$width.'%">';
        }
        $colGroup .= '</colgroup>';

        $out = "<h2>{$title}</h2>";
        $out .= '<p class="section-meta">'.e(number_format(count($rows)).' '.__('baris').' · '.__('kolom').': '.count($header)).'</p>';

        foreach (array_chunk($rows, 200) as $chunk) {
            $out .= '<table>'.$colGroup.$headHtml.'<tbody>';
            foreach ($chunk as $row) {
                $out .= '<tr>';
                foreach (array_keys($header) as $index) {
                    $out .= '<td>'.e(self::pdfCell($row[$index] ?? null)).'</td>';
                }
                $out .= '</tr>';
            }
            $out .= '</tbody></table>';
        }

        return $out.'<div class="page-break"></div>';
    }

    /**
     * Lebar per kolom dalam persen, totalnya 100.
     *
     * @param  array<int, mixed>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, float>
     */
    protected static function pdfColumnWidths(array $header, array $rows): array
    {
        $columns = count($header);

        if ($columns === 0) {
            return [];
        }

        // Panjang isi tiap kolom, dibatasi 60 karakter supaya satu sel
        // panjang tidak mengambil seluruh lebar tabel.
        $weights = [];

        foreach (array_keys($header) as $index) {
            $longest = mb_strlen(ExportText::heading((string) ($header[$index] ?? '')));

            foreach (array_slice($rows, 0, 60) as $row) {
                $length = mb_strlen(self::pdfCell($row[$index] ?? null));
                $longest = max($longest, $length);
            }

            $weights[$index] = max(6, min(60, $longest));
        }

        $total = array_sum($weights);

        if ($total <= 0) {
            return array_fill(0, $columns, 100 / $columns);
        }

        $widths = [];

        foreach ($weights as $index => $weight) {
            $widths[$index] = round(($weight / $total) * 100, 2);
        }

        // Bulatkan ke 2 desimal, lalu benahi selisih pembulatan di kolom
        // TERAKHIR supaya totalnya persis 100 -- kalau tidak, browser dan
        // Dompdf bisa menggeser tabel sedikit ke kiri.
        $rounded = array_map(fn (float $w): float => round($w, 2), $widths);
        $diff = round(100 - array_sum($rounded), 2);

        if ($diff !== 0.0) {
            $last = array_key_last($rounded);
            $rounded[$last] = round($rounded[$last] + $diff, 2);
        }

        return $rounded;
    }

    /**
     * Isi satu sel PDF.
     *
     * TIDAK dipotong. Teks panjang dibiarkan utuh -- di PDF teks panjang
     * membungkus sendiri di dalam sel (lihat `pdfCss()`), jadi memotongnya
     * cuma menghilangkan data.
     */
    protected static function pdfCell(mixed $value): string
    {
        return ExportText::value($value);
    }

    /**
     * Rapikan isi sheet XLSX: judul kolom DAN nilai sel dibersihkan dari
     * "_" dan "/" (lihat `App\Support\ExportText\ExportText`).
     *
     * DULU baris judul dilewati ("itu untuk mesin analisis"). Sekarang
     * judul ikut dirapikan, karena dokumen unduhan harus enak dibaca manusia
     * -- dan Aturan "tanpa underscore, tanpa slash" berlaku untuk XLSX
     * maupun PDF, tanpa kecuali.
     */
    protected static function humanizeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $lastRow = $sheet->getHighestRow();
        if ($lastRow < 1) {
            return;
        }

        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Baris 1 = judul kolom.
        for ($col = 1; $col <= $lastCol; $col++) {
            $cell = $sheet->getCell([$col, 1]);
            $value = self::cellString($cell);

            if ($value === null || $value === '') {
                continue;
            }

            $clean = ExportText::heading($value);
            if ($clean !== $value) {
                $cell->setValueExplicit($clean, DataType::TYPE_STRING);
            }
        }

        // Baris 2+ = isi data.
        for ($row = 2; $row <= $lastRow; $row++) {
            for ($col = 1; $col <= $lastCol; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $value = self::cellString($cell);

                if ($value === null || $value === '') {
                    continue;
                }

                $clean = ExportText::value($value);
                if ($clean !== $value) {
                    $cell->setValueExplicit($clean, DataType::TYPE_STRING);
                }
            }
        }
    }

    /**
     * Teks polos dari satu sel, atau null kalau selnya bukan teks.
     *
     * reader PhpSpreadsheet mengembalikan objek `RichText` untuk sel string
     * (kecuali kalau dipakai `setReadDataOnly(true)`). Kalau nilainya dipakai
     * apa adanya, tipe check `is_string()` gagal -- dan seluruh pembersihan
     * teks dilewati TANPA ERROR. Ini bentuk "diam" yang paling berbahaya.
     */
    protected static function cellString(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): ?string
    {
        $value = $cell->getValue();

        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $value = $value->getPlainText();
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Nama sheet Excel dari label dataset.
     *
     * Excel hanya menerima 31 karakter dan beberapa karakter terlarang
     * (`*`, `:`, `/`, `\`, `?`, `[`, `]`). Karena label juga ikut aturan
     * "tanpa underscore, tanpa slash", cleaned dulu baru dipotong.
     */
    protected static function sheetTitle(string $label): string
    {
        $clean = ExportText::clean($label, '-');
        $clean = (string) preg_replace('/[*:\/\\\\?\[\]]/', '-', $clean);

        return mb_substr($clean, 0, 31);
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
