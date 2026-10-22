<?php

namespace App\Filament\Shared\Exports\BaseExporter;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Dasar untuk semua exporter di panel admin.
 *
 * Kenapa ada kelas dasar:
 *
 *   - 58 exporter (satu per tabel aplikasi) hanya berbeda di SATU hal: model
 *     yang diekspor. Kolomnya, notifikasi selesai, dan formatnya sama.
 *     Tanpa kelas dasar, 58 file mengulang hal yang sama -- dan yang paling
 *     lambat terlihat justru yang tidak ikut berubah.
 *   - Kolomnya DITURUNKAN dari skema tabel, jadi exporter baru tidak perlu
 *     daftar kolom manual. Kalau tabelnya berubah, ekspornya ikut berubah.
 *
 * Kenapa kolom diturunkan dari skema, bukan ditulis manual:
 *
 *   Pusat Unduhan Data harus LENGKAP: satu exporter per tabel aplikasi,
 *   dan tabelnya bisa bertambah. Daftar kolom manual di 58 file adalah
 *   58 tempat yang bisa terlihat benar padahal sudah BASI (kolom baru
 *   tidak ikut terekpor).
 */
abstract class BaseExporter extends Exporter
{
    /**
     * Model yang diekspor.
     *
     * Diisi oleh masing-masing subclass. Kalau kosong, Filament menebak
     * dari nama class (`CategoryExporter` -> `App\Models\Category`) -- tapi
     * tebakan itu sering meleset karena modelnya berada di sub-namespace
     * (`App\Models\Catalog\Category`), jadi setiap subclass menyebut modelnya
     * secara eksplisit.
     */
    protected static ?string $model = null;

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        if (static::$model !== null) {
            return static::$model;
        }

        return parent::getModel();
    }

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        $model = static::getModel();

        if (! class_exists($model)) {
            return [];
        }

        $table = (new $model)->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        return collect(Schema::getColumnListing($table))
            ->reject(fn (string $column): bool => in_array($column, ['password', 'remember_token'], true))
            ->map(fn (string $column): ExportColumn => ExportColumn::make($column))
            ->all();
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return __('Ekspor selesai. Berkasnya bisa diunduh dari Pusat Unduhan Data.');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('Ekspor selesai');
    }
}
