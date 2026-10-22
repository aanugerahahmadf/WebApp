<?php

namespace Tests\Feature\Admin\CatalogTablesShowIdTest;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Livewire\Admin\CatalogTables\CategoryTable;
use App\Livewire\Admin\CatalogTables\PackageTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kolom ID di tiga tabel katalog panel admin.
 *
 * Permintaan pemilik: tabel harus menampilkan ID-nya, supaya urutannya
 * terbaca. Tiga tabel:
 *
 *   - `CatalogResource` (tabel katalog)      -> `->defaultSort('id')` (naik)
 *   - `CategoryTable`     (tabel kategori)   -> `->defaultSort('id')` (naik)
 *   - `PackageTable`      (tabel paket)      -> `->defaultSort('sort_order')`
 *
 * Kolom ID harus yang PERTAMA (di sebelah kiri checkbox pilihan), supaya
 * mata langsung baca: "ini baris nomor berapa".
 */
class CatalogTablesShowIdTest extends TestCase
{
    // Komponen tabel Livewire menjalankan query-nya saat dirender -- jadi
    // tabelnya harus benar-benar ada (RefreshDatabase).
    use RefreshDatabase;

    public function test_catalog_resource_starts_with_the_id_column(): void
    {
        $table = CatalogResource::table($this->makeTable());

        $columns = array_values($table->getColumns());

        $this->assertNotEmpty($columns);
        $this->assertSame('id', $columns[0]->getName(), 'Kolom ID harus yang pertama di tabel katalog.');
    }

    public function test_category_table_starts_with_the_id_column(): void
    {
        $table = Livewire::test(CategoryTable::class)->instance()->getTable();

        $columns = array_values($table->getColumns());

        $this->assertNotEmpty($columns);
        $this->assertSame('id', $columns[0]->getName(), 'Kolom ID harus yang pertama di tabel kategori.');
    }

    public function test_package_table_starts_with_the_id_column(): void
    {
        $table = Livewire::test(PackageTable::class)->instance()->getTable();

        $columns = array_values($table->getColumns());

        $this->assertNotEmpty($columns);
        $this->assertSame('id', $columns[0]->getName(), 'Kolom ID harus yang pertama di tabel paket.');
    }

    /**
     * ID harus bisa dicari DAN diurutkan -- kalau tidak, kolom ID hanya
     * hiasan.
     */
    public function test_the_id_column_is_searchable_and_sortable(): void
    {
        foreach ([
            fn () => CatalogResource::table($this->makeTable()),
            fn () => Livewire::test(CategoryTable::class)->instance()->getTable(),
            fn () => Livewire::test(PackageTable::class)->instance()->getTable(),
        ] as $resolve) {
            $column = collect(array_values($resolve()->getColumns()))->first(fn ($c): bool => $c->getName() === 'id');

            $this->assertNotNull($column);
            $this->assertTrue($column->isSearchable(), 'Kolom ID harus bisa dicari.');
            $this->assertTrue($column->isSortable(), 'Kolom ID harus bisa diurutkan.');
        }
    }

    /**
     * Tabel katalog & kategori diurutkan ID NAIK: ID 1 di baris paling atas.
     */
    public function test_catalog_and_category_tables_sort_by_id_ascending(): void
    {
        $catalog = CatalogResource::table($this->makeTable());
        $this->assertSame('id', $catalog->getDefaultSortColumn());
        $this->assertSame('asc', $catalog->getDefaultSortDirection(), 'ID harus urut NAIK.');

        $category = Livewire::test(CategoryTable::class)->instance()->getTable();
        $this->assertSame('id', $category->getDefaultSortColumn());
    }

    private function makeTable(): Table
    {
        return Table::make(new class extends \Filament\Resources\Pages\ManageRecords
        {
            public function getTable(): Table
            {
                return Table::make($this);
            }
        });
    }
}
