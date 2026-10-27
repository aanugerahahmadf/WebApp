<?php

namespace Tests\Feature\Admin\DatabaseStudioTest;

use App\Filament\Admin\Resources\DatabaseStudioResource\Pages\CreateDatabaseStudio\CreateDatabaseStudio;
use App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ListDatabaseStudio\ListDatabaseStudio;
use App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ViewDatabaseStudio\ViewDatabaseStudio;
use App\Models\Admin\Admin;
use App\Models\Category\Category;
use App\Models\DatabaseStudio\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseStudioTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.studio@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function test_super_admin_can_access_database_studio_index_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/database-studio');

        $response->assertSuccessful();
    }

    public function test_super_admin_can_access_create_database_studio_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/database-studio/create');

        $response->assertSuccessful();
    }

    public function test_super_admin_can_access_view_database_studio_page(): void
    {
        $migration = Migration::first();
        $this->assertNotNull($migration);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get("/admin/database-studio/{$migration->id}");

        $response->assertSuccessful();
    }

    public function test_admin_panel_renders_language_switcher_in_the_sidebar(): void
    {
        // PERUBAHAN PERILAKU (2026-10-25): nama test tadinya "..._in_topbar".
        // Switcher bahasa panel admin pindah dari topbar ke DASAR sidebar
        // (bersama blok profil). Yang dijaga tetap sama: id-nya unik, jadi
        // keberadaannya membuktikan switcher dirender tepat satu kali di
        // halaman mana pun.
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/database-studio');

        $response->assertSuccessful();
        $response->assertSee('id="filament-language-switcher"', false);
    }

    public function test_admin_panel_renders_notification_sound_component(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/database-studio');

        $response->assertSuccessful();
        $response->assertSee('id="filament-notification-sound"', false);
        $response->assertSee('playNotificationSound', false);
    }

    public function test_list_page_has_grouped_header_actions_and_view_action_on_table(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->assertTableActionExists('create')
            ->assertTableActionExists('run_migration')
            ->assertTableActionExists('rollback_migration')
            ->assertTableActionExists('run_seeder')
            ->assertTableActionExists('run_factory')
            ->assertTableActionExists('run_terminal_artisan')
            ->assertTableActionExists('check_db_status')
            ->assertTableActionExists('export_csv')
            ->assertTableActionExists('export_json')
            ->assertTableActionExists('view')
            ->assertTableActionExists('download_file');
    }

    public function test_run_migration_action_executes_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_migration', data: [
                'database' => 'sqlite',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();
    }

    public function test_rollback_migration_action_executes_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('rollback_migration', data: [
                'database' => 'sqlite',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();
    }

    public function test_run_seeder_action_executes_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_seeder', data: [
                'database' => 'sqlite',
                'seeder' => 'RolesAndPermissionsSeeder',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Admin::class,
            'notifiable_id' => $this->superAdmin->id,
        ]);

        $notification = $this->superAdmin->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Seeder Selesai Dijalankan', $notification->data['title'] ?? '');
        $this->assertNotEmpty($notification->data['actions'] ?? []);
    }

    public function test_run_factory_action_generates_dummy_data(): void
    {
        $initialCount = Category::count();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_factory', data: [
                'database' => 'sqlite',
                'model' => 'Category',
                'count' => 2,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertEquals($initialCount + 2, Category::count());

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Admin::class,
            'notifiable_id' => $this->superAdmin->id,
        ]);

        $notification = $this->superAdmin->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Data Dummy Berhasil Dibuat', $notification->data['title'] ?? '');
        $actions = $notification->data['actions'] ?? [];
        $this->assertNotEmpty($actions);
        $this->assertStringContainsString('/admin/catalogs', $actions[0]['url'] ?? '');
    }

    public function test_run_terminal_artisan_action_executes_safely(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_terminal_artisan', data: [
                'command' => 'optimize:clear',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();
    }

    /**
     * Prefiks `php artisan` harus dibuang sebelum perintah menyentuh Artisan.
     *
     * Ini bug yang sama seperti di halaman Terminal, tapi di tempat yang berbeda
     * -- jadi test Terminal saja TIDAK menjaganya. Action ini punya salinan
     * logika normalisasinya sendiri, dan salinan bisa menyimpang tanpa test
     * ini yang melihat: `TerminalPageTest` akan tetap hijau meski
     * `ListDatabaseStudio` kembali ke `explode(' ', $command)[0]`.
     *
     * Yang diuji bukan output pesannya, tapi bahwa prefiks tidak lagi dibaca
     * sebagai nama command: `Artisan::call('php artisan optimize:clear')`
     * melempar `The command "php" does not exist.`
     */
    public function test_run_terminal_artisan_action_strips_shell_prefixes(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_terminal_artisan', data: [
                'command' => 'php artisan optimize:clear',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified();
    }

    /**
     * Blacklist harus tetap berlaku meski admin mengetik dengan prefiks.
     *
     * Kalau normalisasi dijalankan SETELAH cek blacklist, `php artisan tinker`
     * lolos (karena `explode(' ', ...)` pertama memberi `php`) dan command
     * interaktif yang memang tidak bisa jalan via web ikut terpicu. Test ini
     * mengunci urutannya: normalisasi dulu, baru cek.
     *
     * Yang diperiksa adalah APA YANG DIKIRIM KE ARTISAN, bukan isi notifikasi.
     * Notifikasi Filament tidak muncul di `effects` pada versi ini, jadi
     * `assertNotified()` hanya membuktikan "sesuatu dikirim" -- dan both jalur
     * (blokir maupun exception) mengirim notifikasi, jadi test itu akan hijau
     * untuk perilaku yang salah. Spy pada `Artisan` yang bisa membedakan: kalau
     * prefiks tidak ternormalisasi, string yang diterima `Artisan::call()`
     * akan persis `php artisan tinker`.
     */
    public function test_run_terminal_artisan_action_still_blocks_tinker_with_prefix(): void
    {
        Artisan::spy();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_terminal_artisan', data: [
                'command' => 'php artisan tinker',
            ])
            ->assertHasNoTableActionErrors();

        // Penting: `call()` harus DIPANGGIL 0 KALI. Kalau blacklist bocor,
        // `Artisan::call('tinker')` sempat berjalan -- dan `tinker` yang
        // interaktif akan menggantung atau membuat shell session di produksi.
        Artisan::shouldNotHaveReceived('call');
    }

    /**
     * Prefiks yang tidak dinormalisasi akan sampai ke Artisan apa adanya.
     *
     * Ini pasangan langsung dari test di atas: yang satu mengunci "tidak
     * sampai", yang ini mengunci "sampai, tapi sudah benar bentuknya".
     */
    public function test_run_terminal_artisan_action_passes_normalized_command_to_artisan(): void
    {
        Artisan::spy();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_terminal_artisan', data: [
                'command' => 'php artisan migrate:status --database=sqlite',
            ])
            ->assertHasNoTableActionErrors();

        Artisan::shouldHaveReceived('call')
            ->once()
            ->with('migrate:status --database=sqlite');
    }

    /**
     * Input yang hanya berisi prefiks tidak boleh sampai ke Artisan.
     *
     * Setelah normalisasi `php artisan` adalah string kosong. Kalau string
     * kosong diteruskan apa adanya, `Artisan::call('')` melempar
     * `The command "" does not exist.` dan user melihat pesan error untuk
     * input yang sebenarnya berarti "tidak ada perintah".
     */
    public function test_run_terminal_artisan_action_rejects_prefix_only_input(): void
    {
        Artisan::spy();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('run_terminal_artisan', data: [
                'command' => 'php artisan',
            ])
            ->assertHasNoTableActionErrors();

        Artisan::shouldNotHaveReceived('call');
    }

    public function test_check_db_status_action_notifies_connection_states(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('check_db_status')
            ->assertHasNoTableActionErrors()
            ->assertNotified();
    }

    public function test_export_csv_action_executes_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('export_csv')
            ->assertHasNoTableActionErrors();
    }

    public function test_export_json_action_executes_successfully(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListDatabaseStudio::class)
            ->callTableAction('export_json')
            ->assertHasNoTableActionErrors();
    }

    public function test_create_page_generates_migration_file(): void
    {
        $uniqueName = 'test_create_dummy_table_' . time();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateDatabaseStudio::class)
            ->fillForm([
                'item_type' => 'migration',
                'mode' => 'generate',
                'migration_name' => $uniqueName,
                'migration_table' => 'dummy_table',
                'migration_type' => 'create',
                'target_database' => 'sqlite',
                'run_immediately' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $createdFiles = File::glob(database_path("migrations/*_{$uniqueName}.php"));
        $this->assertNotEmpty($createdFiles);

        foreach ($createdFiles as $file) {
            File::delete($file);
        }
    }

    public function test_create_page_handles_migration_file_upload(): void
    {
        Storage::fake('local');
        $fileName = '2026_10_18_999999_test_upload_migration.php';
        $content = '<?php use Illuminate\Database\Migrations\Migration; return new class extends Migration { public function up(): void {} public function down(): void {} };';
        $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
        $destinationPath = database_path('migrations/' . $fileName);

        try {
            Livewire::actingAs($this->superAdmin, 'admin')
                ->test(CreateDatabaseStudio::class)
                ->fillForm([
                    'item_type' => 'migration',
                    'mode' => 'upload',
                    'uploaded_file' => $uploadedFile,
                    'target_database' => 'sqlite',
                    'run_immediately' => false,
                ])
                ->call('create')
                ->assertHasNoFormErrors()
                ->assertNotified();

            $this->assertTrue(File::exists($destinationPath));
        } finally {
            if (File::exists($destinationPath)) {
                File::delete($destinationPath);
            }
        }
    }

    public function test_create_page_generates_seeder_file(): void
    {
        $uniqueName = 'TestDummy' . time() . 'Seeder';

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateDatabaseStudio::class)
            ->fillForm([
                'item_type' => 'seeder',
                'mode' => 'generate',
                'seeder_name' => $uniqueName,
                'target_database' => 'sqlite',
                'run_immediately' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $filePath = database_path("seeders/{$uniqueName}.php");
        $this->assertTrue(File::exists($filePath));

        File::delete($filePath);
    }

    public function test_create_page_handles_seeder_file_upload(): void
    {
        Storage::fake('local');
        $fileName = 'TestUploadCustomSeeder.php';
        $content = '<?php namespace Database\Seeders; use Illuminate\Database\Seeder; class TestUploadCustomSeeder extends Seeder { public function run(): void {} }';
        $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
        $destinationPath = database_path('seeders/' . $fileName);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateDatabaseStudio::class)
            ->fillForm([
                'item_type' => 'seeder',
                'mode' => 'upload',
                'uploaded_file' => $uploadedFile,
                'target_database' => 'sqlite',
                'run_immediately' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertTrue(File::exists($destinationPath));

        File::delete($destinationPath);
    }

    public function test_create_page_generates_factory_file(): void
    {
        $uniqueName = 'TestDummy' . time() . 'Factory';

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateDatabaseStudio::class)
            ->fillForm([
                'item_type' => 'factory',
                'mode' => 'generate',
                'factory_name' => $uniqueName,
                'factory_model' => 'User',
                'target_database' => 'sqlite',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $filePath = database_path("factories/{$uniqueName}.php");
        $this->assertTrue(File::exists($filePath));

        File::delete($filePath);
    }

    public function test_create_page_handles_factory_file_upload(): void
    {
        Storage::fake('local');
        $fileName = 'TestUploadCustomFactory.php';
        $content = '<?php namespace Database\Factories; use Illuminate\Database\Eloquent\Factories\Factory; class TestUploadCustomFactory extends Factory { public function definition(): array { return []; } }';
        $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
        $destinationPath = database_path('factories/' . $fileName);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateDatabaseStudio::class)
            ->fillForm([
                'item_type' => 'factory',
                'mode' => 'upload',
                'uploaded_file' => $uploadedFile,
                'target_database' => 'sqlite',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertTrue(File::exists($destinationPath));

        File::delete($destinationPath);
    }
}
