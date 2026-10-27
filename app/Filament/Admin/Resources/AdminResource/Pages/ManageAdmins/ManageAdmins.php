<?php

namespace App\Filament\Admin\Resources\AdminResource\Pages\ManageAdmins;

use App\Filament\Admin\Resources\AdminResource\AdminResource;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Table;

/**
 * Satu-satunya halaman untuk resource ini -- tidak dipisah jadi
 * List/Edit/Create.
 *
 * Alasannya sama seperti UserResource: baris `admins` hanya dibaca untuk
 * siapa yang aktif dan role-nya, jadi pola slide-overpun lebih cepat daripada
 * halaman terpisah. `ManageRecords` memberi Create + Edit dalam slide-over dan
 * tetap menyediakan paginasi, bulk action, dan filter.
 *
 * Konvensi folder-per-class project ini: folder `ManageAdmins` berisi
 * `ManageAdmins.php`. FQCN-nya karena itu berakhir dengan nama class-nya juga,
 * dan itulah yang harus dipakai di `getPages()`.
 */
class ManageAdmins extends ManageRecords
{
    // Untuk `exporterClass()` dan `standardTableHeaderActions()` -- tombol
    // "Tambah" + "Ekspor" ada di HEADER TABEL, bukan header halaman.
    use HasStandardActions;


    protected static string $resource = AdminResource::class;

    public function getTitle(): string
    {
        return __('Admin Tim');
    }

    /**
     * Aksi header TABEL: "Tambah Admin" + "Ekspor".
     *
     * "Tambah Admin" TIDAK boleh hilang. `AdminResource` tidak punya halaman
     * `create` (cuma `index`), jadi tidak ada halaman yang bisa-this-open --
     * tanpa tombol ini, satu-satunya cara menambah admin adalah
     * `php artisan` atau menyunting database, dan biasanya kita berakhir
     * menulis admin ke tabel `users`.
     *
     * Labelnya "Tambah Admin" (bukan "Tambah Admin Tim") -- dan letaknya
     * di header tabel, sama seperti tombol lain.
     */
    public function table(Table $table): Table
    {
        $actions = static::standardTableHeaderActions();

        $actions[0]->label(__('Tambah Admin'));

        return static::getResource()::table($table)
            ->headerActions($actions);
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
