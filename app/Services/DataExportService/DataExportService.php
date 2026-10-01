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
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Bus;
use ZipArchive;

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
     * "Ekspor Data": siapkan model Export, jalankan rantai job yang sama
     * (PrepareCsvExport → CreateXlsxFile → ExportCompletion). Dengan antrean
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

        $formats = [ExportFormat::Xlsx];
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
            app(ExportCompletion::class, [
                'export' => $export,
                'columnMap' => $columnMap,
                'formats' => $formats,
                'options' => [],
            ]),
        ])->dispatch();

        return $export->fresh();
    }

    /**
     * Jalankan export native untuk dataset terpilih lalu gabungkan XLSX-nya
     * ke satu ZIP. Mengembalikan path absolut file ZIP.
     *
     * @param  array<int, string>  $keys
     */
    public static function buildZip(array $keys, User $user): string
    {
        $keys = array_values(array_intersect($keys, array_keys(self::datasets())));

        if ($keys === []) {
            throw new \InvalidArgumentException('Pilih minimal satu dataset.');
        }

        $zipPath = storage_path('app/private/data-exports/'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip ZIP.');
        }

        foreach ($keys as $key) {
            $dataset = self::datasets()[$key];
            $export = self::runNativeExport($dataset['exporter'], $user);

            $disk = $export->getFileDisk();
            foreach ($disk->files($export->getFileDirectory()) as $file) {
                if (str_ends_with(strtolower($file), '.xlsx')) {
                    $zip->addFile($disk->path($file), $dataset['filename']);
                    break;
                }
            }
        }

        $zip->close();

        return $zipPath;
    }
}
