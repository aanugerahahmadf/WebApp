<?php

namespace Tests\Feature\Admin\StudioHeaderActionsTest;

use App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource;
use App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ListDatabaseStudio\ListDatabaseStudio;
use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ListDeveloperStudio\ListDeveloperStudio;
use App\Models\Admin\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Aksi Studio Basis Data & Studio API.
 *
 * Dua hal yang dijaga di sini:
 *
 * 1. **Ikon yang dirujuk harus ADA.** Ikon di `public/images/icons/` punya
 *    warnanya sendiri, jadi `->icon('/images/icons/x.svg')` yang menunjuk
 *    file yang tidak ada = gambar rusak di tombol, tanpa error. Waktu dulu
 *    tujuh ikon seperti itu terpakai di dua halaman studio.
 *
 * 2. **Aksinya di header TABEL**, bukan header halaman. Header halaman cuma
 *    ada judul + breadcrumb; deretan tombol selebar layar menutupi
 *    judulnya. Header tabel juga tempatnya tombol Ekspor dan kotak pencarian.
 */
final class StudioHeaderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_setiap_ikon_yang_dirujuk_ada(): void
    {
        $referenced = [];

        foreach ([
            app_path('Filament/Admin'),
            resource_path('views/Admin'),
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                    continue;
                }

                preg_match_all(
                    '#images/icons/([a-z0-9_\-]+\.svg)#',
                    (string) file_get_contents($file->getPathname()),
                    $matches
                );

                foreach ($matches[1] as $icon) {
                    $referenced[$icon] = true;
                }
            }
        }

        $this->assertNotEmpty($referenced, 'Harus ada ikon yang dirujuk.');

        $missing = [];

        foreach (array_keys($referenced) as $icon) {
            if (! file_exists(public_path('images/icons/'.$icon))) {
                $missing[] = $icon;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Ikon yang dirujuk tapi filenya tidak ada (jadi gambar rusak di tombol): '.implode(', ', $missing),
        );
    }

    public function test_aksi_studio_basis_data_di_header_tabel(): void
    {
        // Aksinya di HEADER TABEL, bukan header halaman.
        Livewire::actingAs($this->admin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->assertSuccessful()
            ->assertTableActionExists('create')
            ->assertTableActionExists('run_migration')
            ->assertTableActionExists('rollback_migration')
            ->assertTableActionExists('run_seeder')
            ->assertTableActionExists('run_factory')
            ->assertTableActionExists('run_terminal_artisan')
            ->assertTableActionExists('check_db_status')
            ->assertTableActionExists('export_csv')
            ->assertTableActionExists('export_json');
    }

    public function test_aksi_studio_api_di_header_tabel(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(ListDeveloperStudio::class)
            ->assertSuccessful()
            ->assertTableActionExists('create_new')
            ->assertTableActionExists('sync_now')
            ->assertTableActionExists('run_graphql_query')
            ->assertTableActionExists('check_grpc_status')
            ->assertTableActionExists('route_cache')
            ->assertTableActionExists('route_clear')
            ->assertTableActionExists('optimize_clear')
            ->assertTableActionExists('export_json')
            ->assertTableActionExists('export_csv');
    }

    public function test_kedua_halaman_memakai_exporter(): void
    {
        // Tanpa exporter, tombol "Ekspor" tidak muncul sama sekali.
        $this->assertNotNull(
            DatabaseStudioResource::exporterClass(),
            'Studio Basis Data harus punya exporter.',
        );

        $this->assertNotNull(
            DeveloperStudioResource::exporterClass(),
            'Studio API harus punya exporter.',
        );
    }
}
