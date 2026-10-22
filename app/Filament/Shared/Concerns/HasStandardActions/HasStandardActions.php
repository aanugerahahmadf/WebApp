<?php

namespace App\Filament\Shared\Concerns\HasStandardActions;

use App\Filament\Admin\Exports\AdminExporter\AdminExporter;
use App\Filament\Admin\Exports\ApiKeyExporter\ApiKeyExporter;
use App\Filament\Admin\Exports\CatalogExporter\CatalogExporter;
use App\Filament\Admin\Exports\DeveloperStudioEndpointExporter\DeveloperStudioEndpointExporter;
use App\Filament\Admin\Exports\DiscountExporter\DiscountExporter;
use App\Filament\Admin\Exports\FmAppSettingExporter\FmAppSettingExporter;
use App\Filament\Admin\Exports\HelpExporter\HelpExporter;
use App\Filament\Admin\Exports\MigrationExporter\MigrationExporter;
use App\Filament\Admin\Exports\OrderExporter\OrderExporter;
use App\Filament\Admin\Exports\PaymentGatewayExporter\PaymentGatewayExporter;
use App\Filament\Admin\Exports\PaymentMethodExporter\PaymentMethodExporter;
use App\Filament\Admin\Exports\PrivacyPolicyExporter\PrivacyPolicyExporter;
use App\Filament\Admin\Exports\ReferenceOptionExporter\ReferenceOptionExporter;
use App\Filament\Admin\Exports\ReportExporter\ReportExporter;
use App\Filament\Admin\Exports\ReviewExporter\ReviewExporter;
use App\Filament\Admin\Exports\TermsOfServiceExporter\TermsOfServiceExporter;
use App\Filament\Admin\Exports\TransactionExporter\TransactionExporter;
use App\Filament\Admin\Exports\UserExporter\UserExporter;
use App\Filament\Admin\Exports\VoucherExporter\VoucherExporter;
use App\Filament\Admin\Exports\WeddingDecorationPolicyExporter\WeddingDecorationPolicyExporter;
use App\Filament\Admin\Exports\WishlistExporter\WishlistExporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\CreateAction as TablesCreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ExportAction as TablesExportAction;
use Filament\Tables\Actions\ViewAction;

/**
 * Aksi STANDAR untuk resource panel admin.
 *
 * Kenapa ini trait, bukan ditulis di 20 resource:
 *
 *   - Aksi dasar Filament (Tambah, Lihat, Ubah, Hapus, Export) sama di semua
 *     resource. Ditulis manual di 20 file berarti 20 tempat yang bisa lupa
 *     satu aksi -- dan gejalanya HALUS: tabelnya tetap tampil, kolomnya tetap
 *     ada, cuma tombolnya tidak ada. Tidak ada error, tidak ada stack trace.
 *   - Resource yang tidak punya halaman `create` (mis. Studio Basis Data yang
 *     jalurnya lewat tab) tidak boleh mendapat tombol Tambah yang lead ke 404.
 *     Jadi aksinya dihitung dari `getPages()` resource, bukan dari daftar tetap.
 *
 * Resource yang butuh aksi ekstra (mis. aksi cepat ubah status pesanan)
 * menambahkannya sendiri lewat `getHeaderActions()` / `->actions()`, karena
 * trait ini hanya menangani bagian yang repeatable.
 */
trait HasStandardActions
{
    /**
     * Aksi baris: Lihat / Ubah / Hapus.
     *
     * Ketiganya SELALU ada. View dan Ubah di sini berupa slide-over (form
     * terisi data record), jadi tidak butuh halaman `view`/`edit` terpisah
     * -- resource bergaya ManageRecords (hanya punya halaman index) tetap
     * bisa melihat dan mengubah recordnya.
     *
     * Dirangkum dalam satu ActionGroup dengan tombol ikon -- sama seperti
     * tabel kategori dan paket. Action-nya tetap bisa dipanggil lewat
     * namanya (`callTableAction('view', ...)`) karena action di dalam group
     * ikut terdaftar di tabel.
     *
     * @return array<\Filament\Tables\Actions\Action>
     */
    public static function standardTableActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make()
                    ->iconButton()
                    ->color('info')
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete')),
            ])->tooltip(__('Actions')),
        ];
    }

    /**
     * Aksi HEADER TABEL: Tambah + Ekspor.
     *
     * Kenapa di header tabel, bukan header halaman: header halaman cuma ada
     * judul + breadcrumb, dan deretan tombol selebar layar itu menutupi
     * judulnya. Header tabel juga tempatnya kotak pencarian, jadi semua
     * kendali tabelnya berderet di satu baris.
     *
     * @return array<\Filament\Tables\Actions\Action>
     */
    public static function standardTableHeaderActions(): array
    {
        $actions = [];

        $actions[] = TablesCreateAction::make()
            ->icon('/images/icons/plus.svg');

        $exporter = static::exporterClass();

        if ($exporter !== null) {
            $actions[] = TablesExportAction::make()
                ->exporter($exporter)
                ->icon('/images/icons/download.svg')
                ->color('success');
        }

        return $actions;
    }

    /**
     * Aksi massal: hapus baris yang dicentang.
     *
     * @return array<BulkAction>
     */
    public static function standardBulkActions(): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }

    /**
     * Eksporter milik resource ini.
     *
     * Resource tanpa exporter (mis. Studio Basis Data, yang isinya berkas
     * migration di disk -- bukan baris tabel) mengembalikan `null`, sehingga
     * tombol Ekspor tidak muncul sama sekali. Tombol yang selalu gagal lebih
     * buruk daripada tombol yang tidak ada.
     *
     * @return class-string<\Filament\Actions\Exports\Exporter>|null
     */
    public static function exporterClass(): ?string
    {
        return match (static::class) {
            \App\Filament\Admin\Resources\AdminResource\AdminResource::class => AdminExporter::class,
            \App\Filament\Admin\Resources\ApiKeyResource\ApiKeyResource::class => ApiKeyExporter::class,
            \App\Filament\Admin\Resources\CatalogResource\CatalogResource::class => CatalogExporter::class,
            \App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource::class => DeveloperStudioEndpointExporter::class,
            \App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource::class => MigrationExporter::class,
            \App\Filament\Admin\Resources\DiscountResource\DiscountResource::class => DiscountExporter::class,
            \App\Filament\Admin\Resources\HelpResource\HelpResource::class => HelpExporter::class,
            \App\Filament\Admin\Resources\MaintenanceResource\MaintenanceResource::class => FmAppSettingExporter::class,
            \App\Filament\Admin\Resources\OrderResource\OrderResource::class => OrderExporter::class,
            \App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource::class => PaymentGatewayExporter::class,
            \App\Filament\Admin\Resources\PaymentMethodResource\PaymentMethodResource::class => PaymentMethodExporter::class,
            \App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource::class => PrivacyPolicyExporter::class,
            \App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource::class => ReferenceOptionExporter::class,
            \App\Filament\Admin\Resources\ReportResource\ReportResource::class => ReportExporter::class,
            \App\Filament\Admin\Resources\ReviewResource\ReviewResource::class => ReviewExporter::class,
            \App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource::class => TermsOfServiceExporter::class,
            \App\Filament\Admin\Resources\TransactionResource\TransactionResource::class => TransactionExporter::class,
            \App\Filament\Admin\Resources\UserResource\UserResource::class => UserExporter::class,
            \App\Filament\Admin\Resources\VoucherResource\VoucherResource::class => VoucherExporter::class,
            \App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource::class => WeddingDecorationPolicyExporter::class,
            \App\Filament\Admin\Resources\WebsiteMobileCustomizerResource\WebsiteMobileCustomizerResource::class => FmAppSettingExporter::class,
            \App\Filament\Admin\Resources\WishlistResource\WishlistResource::class => WishlistExporter::class,
            default => null,
        };
    }

    /**
     * Aksi header halaman daftar: Tambah + Ekspor.
     *
     * Dipanggil dari `getHeaderActions()` halaman list.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function standardHeaderActions(): array
    {
        $pages = array_keys(static::getResource()::getPages());

        $actions = [];

        if (in_array('create', $pages, true)) {
            $actions[] = CreateAction::make()
                ->icon('/images/icons/plus.svg');
        }

        $exporter = static::getResource()::exporterClass();

        if ($exporter !== null) {
            $actions[] = ExportAction::make()
                ->exporter($exporter);
        }

        return $actions;
    }
}
