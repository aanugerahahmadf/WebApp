<?php

namespace App\Filament\Admin\Resources\DatabaseStudioResource\Pages\CreateDatabaseStudio;

use App\Filament\Admin\Concerns\HandlesFileUpload\HandlesFileUpload;
use App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Halaman "Buat" Studio Basis Data.
 *
 * Halaman ini TIDAK membuat baris di tabel `migrations`. Yang dibuatnya
 * BERKAS: migration, seeder, atau factory.
 *
 * Kenapa tidak lewat form resource biasa: tabel `migrations` cuma punya
 * `migration` dan `batch`, dan keduanya diisi Laravel sendiri saat migrasi
 * dijalankan. Form yang menanyakan "nama file" dan menuliskan ke disk
 * punya bentuk yang sama sekali berbeda -- jadi formnya ditulis di sini,
 * bukan diturunkan dari skema tabel.
 */
class CreateDatabaseStudio extends CreateRecord
{
    use HandlesFileUpload;

    protected static string $resource = DatabaseStudioResource::class;

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Select::make('item_type')
                ->searchable()
                ->label(__('Jenis'))
                ->options([
                    'migration' => __('Migration'),
                    'seeder' => __('Seeder'),
                    'factory' => __('Factory'),
                ])
                ->default('migration')
                ->required()
                ->live(),

            Select::make('mode')
                ->searchable()
                ->label(__('Cara'))
                ->options([
                    'generate' => __('Buat dari templat'),
                    'upload' => __('Unggah berkas'),
                ])
                ->default('generate')
                ->required()
                ->live(),

            // ---------------------------------------------------- MIGRATION --
            TextInput::make('migration_name')
                ->label(__('Nama Migration'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'migration'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'migration'))
                ->helperText(__('Nama tanpa ekstensi, tanpa prefiks timestamp.')),

            TextInput::make('migration_table')
                ->label(__('Nama Tabel'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'migration'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'migration')),

            Select::make('migration_type')
                ->searchable()
                ->label(__('Jenis Perubahan'))
                ->options([
                    'create' => __('Buat Tabel'),
                    'update' => __('Ubah Tabel'),
                ])
                ->default('create')
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'migration'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'migration')),

            // ------------------------------------------------------- SEEDER --
            TextInput::make('seeder_name')
                ->label(__('Nama Seeder'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'seeder'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'seeder')),

            // ------------------------------------------------------ FACTORY --
            TextInput::make('factory_name')
                ->label(__('Nama Factory'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'factory'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'factory')),

            TextInput::make('factory_model')
                ->label(__('Model'))
                ->required(fn (Get $get): bool => $this->isGenerating($get, 'factory'))
                ->visible(fn (Get $get): bool => $this->isGenerating($get, 'factory'))
                ->helperText(__('Nama model yang dipakai factory, mis. User.')),

            // -------------------------------------------------------- UPLOAD --
            FileUpload::make('uploaded_file')
                ->label(__('Berkas'))
                ->disk('local')
                // Nama asli berkas harus dipertahankan: tanpa ini, Livewire
                // menyimpannya sebagai nama hash ("01M5G61VSPQ...php") dan
                // nama berkas yang ditulis ke disk tidak lagi sama dengan
                // yang diunggah admin.
                ->preserveFilenames()
                // TIDAK ada pembatasan `acceptedFileTypes`.
                //
                // Mime type ditebak dari ISI berkas, bukan dari namanya --
                // jadi berkas `.php` bisa terdeteksi sebagai `text/plain`
                // atau `application/octet-stream`. Membatasi jenis berkas
                // membuat berkas yang benar-benar valid ditolak dengan
                // pesan "berkas harus berupa berkas berjenis ...".
                ->required(fn (Get $get): bool => $get('mode') === 'upload')
                ->visible(fn (Get $get): bool => $get('mode') === 'upload')
                ->maxSize(2048),

            // ------------------------------------------------------ LAINNYA --
            Select::make('target_database')
                ->searchable()
                ->label(__('Database'))
                ->options($this->databaseOptions())
                ->default('sqlite')
                ->required(),

            Toggle::make('run_immediately')
                ->label(__('Jalankan Langsung'))
                ->helperText(__('Migration langsung dijalankan setelah berkasnya dibuat.'))
                ->default(false),
        ]);
    }

    /**
     * Buat berkasnya.
     *
     * Method ini menggantikan `CreateRecord::create()`: tidak ada baris
     * tabel yang dibuat, karena tabel `migrations` diisi Laravel sendiri
     * saat migrasi dijalankan.
     */
    public function create(bool $another = false): void
    {
        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('beforeCreate');
        $this->callHook('afterValidate');

        $path = $this->writeFile($data);

        $this->callHook('afterCreate');

        Notification::make()
            ->success()
            ->title(__('Berkas Berhasil Dibuat'))
            ->body(__('Berkas: :path', ['path' => $path]))
            ->send();

        if (($data['run_immediately'] ?? false) && ($data['item_type'] ?? null) === 'migration') {
            $this->runMigration($path, (string) ($data['target_database'] ?? 'sqlite'));
        }
    }

    /**
     * Tulis berkas ke disk dan kembalikan lokasinya.
     *
     * @param  array<string, mixed>  $data
     */
    protected function writeFile(array $data): string
    {
        $itemType = (string) ($data['item_type'] ?? 'migration');
        $mode = (string) ($data['mode'] ?? 'generate');

        return $mode === 'upload'
            ? $this->storeUploadedFile($itemType, $data)
            : $this->generateFile($itemType, $data);
    }

    /**
     * Buat berkas dari templat.
     *
     * @param  array<string, mixed>  $data
     */
    protected function generateFile(string $itemType, array $data): string
    {
        return match ($itemType) {
            'seeder' => $this->generateSeeder($data),
            'factory' => $this->generateFactory($data),
            default => $this->generateMigration($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateMigration(array $data): string
    {
        $name = (string) ($data['migration_name'] ?? 'migration');
        $table = (string) ($data['migration_table'] ?? 'table');
        $type = (string) ($data['migration_type'] ?? 'create');

        $filename = now()->format('Y_m_d_His').'_'.$name.'.php';

        $directory = database_path('migrations');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$filename;

        $body = $type === 'update'
            ? $this->migrationUpdateTemplate($table)
            : $this->migrationCreateTemplate($table);

        File::put($path, $body);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateSeeder(array $data): string
    {
        $name = (string) ($data['seeder_name'] ?? 'Seeder');

        $class = str_ends_with($name, 'Seeder') ? $name : $name.'Seeder';

        $directory = database_path('seeders');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$class.'.php';

        File::put($path, $this->seederTemplate($class));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateFactory(array $data): string
    {
        $name = (string) ($data['factory_name'] ?? 'Factory');

        $class = str_ends_with($name, 'Factory') ? $name : $name.'Factory';

        $model = (string) ($data['factory_model'] ?? 'User');

        $directory = database_path('factories');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$class.'.php';

        File::put($path, $this->factoryTemplate($class, $model));

        return $path;
    }

    /**
     * Simpan berkas yang diunggah ke folder yang benar.
     *
     * @param  array<string, mixed>  $data
     */
    protected function storeUploadedFile(string $itemType, array $data): string
    {
        $uploaded = $this->resolveUploadedFile($data);

        $directory = match ($itemType) {
            'seeder' => database_path('seeders'),
            'factory' => database_path('factories'),
            default => database_path('migrations'),
        };

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$uploaded['name'];

        File::put($path, $uploaded['contents']);

        return $path;
    }

    /**
     * Jalankan migration yang baru dibuat.
     */
    protected function runMigration(string $path, string $database): void
    {
        try {
            Artisan::call('migrate', [
                '--path' => $path,
                '--database' => $database,
            ]);

            Notification::make()
                ->success()
                ->title(__('Migration Berhasil Dijalankan'))
                ->body((string) Artisan::output())
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title(__('Migration Gagal Dijalankan'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function isGenerating(Get $get, string $itemType): bool
    {
        return $get('item_type') === $itemType && $get('mode') === 'generate';
    }

    /**
     * @return array<string, string>
     */
    protected function databaseOptions(): array
    {
        $options = [];

        foreach (array_keys(config('database.connections', [])) as $connection) {
            $options[$connection] = $connection;
        }

        return $options;
    }

    protected function migrationCreateTemplate(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table): void {
                    \$table->id();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
    }

    protected function migrationUpdateTemplate(string $table): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$table}', function (Blueprint \$table): void {
                    \$table->string('nama')->nullable();
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table): void {
                    \$table->dropColumn('nama');
                });
            }
        };

        PHP;
    }

    protected function seederTemplate(string $class): string
    {
        return <<<PHP
        <?php

        namespace Database\Seeders;

        use Illuminate\Database\Seeder;

        class {$class} extends Seeder
        {
            public function run(): void
            {
                //
            }
        }

        PHP;
    }

    protected function factoryTemplate(string $class, string $model): string
    {
        return <<<PHP
        <?php

        namespace Database\Factories;

        use Illuminate\Database\Eloquent\Factories\Factory;

        class {$class} extends Factory
        {
            protected \$model = \\App\\Models\\{$model}::class;

            public function definition(): array
            {
                return [];
            }
        }

        PHP;
    }

    /**
     * Judul halaman BUKAN "Create Database Studio".
     *
     * `CreateRecord::getTitle()` membentuk "Create {plural model label}", dan
     * plural label resource ini "Database Studio" -- hasilnya "Create Database
     * Studio", yang tidak menjelaskan apa pun: halaman ini tidak membuat
     * "studio", ia membuat BERKAS migration/seeder/factory.
     */
    public function getTitle(): string
    {
        return __('Buat Migration / Seeder / Factory');
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
