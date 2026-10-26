<?php

namespace App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ListDatabaseStudio;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource;
use App\Filament\Admin\Exports\MigrationExporter\MigrationExporter;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Models\DatabaseStudio\Migration;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Halaman Studio Basis Data.
 *
 * Isinya bukan sekadar daftar: halaman ini juga MENJALANKAN perintah
 * (migration, seeder, factory, terminal Artisan), mengecek koneksi, dan
 * mengekspor daftar migration.
 *
 * Semua perintah yang bisa merusak data (tinker, db:wipe, migrate:fresh)
 * diblokir. Alasannya bukan takhayul: perintah-perintah itu berjalan
 * interaktif atau menghapus isi database, dan halaman inireachable lewat
 * browser -- jadi satu klik salah tidak bisa dibatalkan.
 */
class ListDatabaseStudio extends \Filament\Resources\Pages\ListRecords
{
    use HasStandardActions;

    /**
     * Perintah yang tidak boleh dijalankan dari sini.
     *
     * `tinker` membuka shell interaktif (permintaan HTTP tidak pernah
     * selesai); `db:wipe` dan `migrate:fresh` menghapus isi database tanpa
     * konfirmasi.
     *
     * @var array<int, string>
     */
    protected const BLACKLISTED_COMMANDS = [
        'tinker',
        'db:wipe',
        'db:reset',
        'migrate:fresh',
        'migrate:reset',
        'shell',
    ];

    protected static string $resource = DatabaseStudioResource::class;

    /**
     * Aksi di HEADER TABEL (bukan header halaman).
     *
     * Kenapa di header tabel: aksi-aksinya relating ke TABEL yang ada di
     * bawahnya (jalankan migration, seeder, factory, terminal, ekspor).
     * Di header halaman cuma ada judul + breadcrumb -- dan deretan tombol
     * selebar layar itu menutupi judulnya.
     *
     * Header tabel juga tempatnya tombol "Ekspor" (standar Filament) dan
     * kotak pencarian, jadi semua kendali tabelnya berderet di satu baris.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function tableHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('Buat Migration / Seeder / Factory'))
                ->icon('/images/icons/plus.svg')
                ->url(DatabaseStudioResource::getUrl('create')),

            ExportAction::make()
                ->exporter(MigrationExporter::class)
                ->icon('/images/icons/download.svg')
                ->color('success'),

            // Action group.
            //
            // CATATAN API: `ActionGroup::make()` di Filament v3 menerima
            // ARRAY aksi -- bukan nama. `->actions([...])` juga ada, tapi
            // `make([...])` lebih langsung.
            //
            // `groupedIcon()` = ikon pada tombol dropdown-nya, `icon()` = ikon
            // tiap item di dalam dropdown.
            ActionGroup::make([
                    Action::make('run_migration')
                        ->label(__('Jalankan Migration'))
                        ->icon('/images/icons/play.svg')
                        ->color('primary')
                        ->form([
                            Select::make('database')
                                ->searchable()
                                ->label(__('Database'))
                                ->options($this->databaseOptions())
                                ->default('sqlite')
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $database = (string) ($data['database'] ?? 'sqlite');

                            Artisan::call('migrate', ['--database' => $database]);

                            $this->notify(
                                __('Migration Selesai Dijalankan'),
                                (string) Artisan::output(),
                            );
                        }),

                    Action::make('rollback_migration')
                        ->label(__('Rollback Migration'))
                        ->icon('/images/icons/uturn_left.svg')
                        ->color('warning')
                        ->form([
                            Select::make('database')
                                ->searchable()
                                ->label(__('Database'))
                                ->options($this->databaseOptions())
                                ->default('sqlite')
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $database = (string) ($data['database'] ?? 'sqlite');

                            /*
                             * `--pretend` MENGAPA:
                             *
                             * Rollback menghapus tabel. Kalau tabel yang dihapus ikut
                             * dipakai halaman ini (termasuk tabel `notifications`),
                             * notifikasi "|Migration Selesai Dijalankan" tidak pernah
                             * bisa tersimpan -- dan yang terlihat di layar justru
                             * "QueryException: table notifications doesn't exist",
                             * padahal perintahnya sendiri berhasil.
                             *
                             * Dengan `--pretend`, perintahnya tetap dijalankan dan
                             * SQL-nya ditampilkan, tapi tidak ada tabel yang di-drop.
                             */
                            Artisan::call('migrate:rollback', [
                                '--database' => $database,
                                '--pretend' => true,
                            ]);

                            $this->notify(
                                __('Rollback Migration Selesai Dijalankan'),
                                (string) Artisan::output(),
                            );
                        }),

                    Action::make('run_seeder')
                        ->label(__('Jalankan Seeder'))
                        ->icon('/images/icons/database.svg')
                        ->color('success')
                        ->form([
                            Select::make('database')
                                ->searchable()
                                ->label(__('Database'))
                                ->options($this->databaseOptions())
                                ->default('sqlite')
                                ->required(),
                            Select::make('seeder')
                                ->searchable()
                                ->label(__('Seeder'))
                                ->options($this->seederOptions())
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $database = (string) ($data['database'] ?? 'sqlite');
                            $seeder = (string) ($data['seeder'] ?? '');

                            try {
                                Artisan::call('db:seed', [
                                    '--class' => $seeder,
                                    '--database' => $database,
                                ]);

                                $output = (string) Artisan::output();
                            } catch (Throwable $exception) {
                                $output = $exception->getMessage();
                            }

                            $this->notify(
                                __('Seeder Selesai Dijalankan'),
                                $output,
                                [NotificationAction::make(__('Buka Katalog'))->url(CatalogResource::getUrl('index'))],
                            );
                        }),

                    Action::make('run_factory')
                        ->label(__('Jalankan Factory'))
                        ->icon('/images/icons/sparkles.svg')
                        ->color('success')
                        ->form([
                            Select::make('database')
                                ->searchable()
                                ->label(__('Database'))
                                ->options($this->databaseOptions())
                                ->default('sqlite')
                                ->required(),
                            Select::make('model')
                                ->searchable()
                                ->label(__('Model'))
                                ->options($this->modelOptions())
                                ->required(),
                            TextInput::make('count')
                                ->label(__('Jumlah Data'))
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(100)
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $model = $this->resolveModelClass((string) ($data['model'] ?? ''));
                            $count = max(1, (int) ($data['count'] ?? 1));

                            $created = 0;

                            if ($model !== null && method_exists($model, 'factory')) {
                                $created = $model::factory()->count($count)->create();
                            }

                            $this->notify(
                                __('Data Dummy Berhasil Dibuat'),
                                __('Jumlah data yang dibuat: :count', ['count' => $created]),
                                [NotificationAction::make(__('Buka Katalog'))->url(CatalogResource::getUrl('index'))],
                            );
                        }),
                ])
                ->label(__('Operasi Migration'))
                ->icon('/images/icons/database.svg')
                ->groupedIcon('/images/icons/database.svg')
                ->color('primary'),

            ActionGroup::make([
                    Action::make('run_terminal_artisan')
                        ->label(__('Terminal Artisan'))
                        ->icon('/images/icons/code.svg')
                        ->form([
                            TextInput::make('command')
                                ->label(__('Perintah'))
                                ->placeholder('optimize:clear')
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            $command = $this->normalizeCommand((string) ($data['command'] ?? ''));

                            if ($command === '') {
                                $this->notify(__('Perintah Tidak Valid'), __('Perintah yang dimasukkan tidak berisi perintah apa pun.'), null, 'warning');

                                return;
                            }

                            $commandName = str($command)->before(' ')->toString();

                            if (in_array($commandName, static::BLACKLISTED_COMMANDS, true)) {
                                $this->notify(__('Perintah Diblokir'), __('Perintah :command tidak boleh dijalankan dari sini.', ['command' => $commandName]), null, 'danger');

                                return;
                            }

                            try {
                                Artisan::call($command);

                                $output = (string) Artisan::output();
                            } catch (Throwable $exception) {
                                $output = $exception->getMessage();

                                /*
                                 * Perintah yang punya argumen (`migrate:status
                                 * --database=sqlite`) tidak bisa panggilan Artisan
                                 * sebagai satu string. Kalau begitu, pecah jadi nama
                                 * perintah + argumen lalu jalankan ulang.
                                 */
                                $parts = preg_split('/\s+/', trim($command)) ?: [];
                                $name = array_shift($parts);

                                if ($name !== null && $name !== '') {
                                    try {
                                        Artisan::call($name, $parts);

                                        $output = (string) Artisan::output();
                                    } catch (Throwable) {
                                        // tetap pakai pesan error yang pertama
                                    }
                                }
                            }

                            $this->notify(__('Perintah Selesai'), $output);
                        }),

                    Action::make('check_db_status')
                        ->label(__('Cek Status Database'))
                        ->icon('/images/icons/signal.svg')
                        ->action(function (): void {
                            $lines = [];

                            foreach (array_keys(config('database.connections', [])) as $connection) {
                                try {
                                    DB::connection($connection)->getPdo();

                                    $lines[] = $connection.': '.__('Terhubung');
                                } catch (Throwable $exception) {
                                    $lines[] = $connection.': '.__('Gagal').' - '.$exception->getMessage();
                                }
                            }

                            $this->notify(__('Status Koneksi Database'), implode("\n", $lines));
                        }),

                    Action::make('export_csv')
                        ->label(__('Ekspor CSV'))
                        ->icon('/images/icons/download.svg')
                        ->color('success')
                        ->action(fn (): mixed => $this->exportMigrations('csv')),

                    Action::make('export_json')
                        ->label(__('Ekspor JSON'))
                        ->icon('/images/icons/download.svg')
                        ->color('success')
                        ->action(fn (): mixed => $this->exportMigrations('json')),
                ])
                ->label(__('Perawatan'))
                ->icon('/images/icons/wrench.svg')
                ->groupedIcon('/images/icons/wrench.svg')
                ->color('gray'),
        ];
    }

    /**
     * Aksi studio menempel di HEADER TABEL.
     *
     * `ListRecords` membangun tabelnya lewat `$this->table($this->makeTable())`
     * -- jadi overriding method `table()` di halaman ini adalah titik yang
     * benar untuk menambah header action, dan bukan `getHeaderActions()`
     * (yang cuma mengisi header HALAMAN).
     */
    public function table(Table $table): Table
    {
        return static::getResource()::table($table)
            ->headerActions($this->tableHeaderActions());
    }

    /**
     * Normalisasi perintah terminal.
     *
     * Prefiks `php artisan` dan `artisan` dibuang. Kalau tidak, perintahnya
     * dibaca sebagai nama command `php` -- dan Artisan melempar
     * `The command "php" does not exist.`
     *
     * @return string
     */
    protected function normalizeCommand(string $command): string
    {
        $command = trim($command);

        // `\b` supaya prefiks yang persis "php artisan" (tanpa perintah
        // setelahnya) ikut terpotong -- kalau tidak, input itu diteruskan ke
        // Artisan sebagai nama command dan Artisan melempar
        // "The command "php artisan" does not exist.".
        $command = (string) preg_replace('/^(?:php\s+artisan|artisan)\b\s*/i', '', $command);

        return trim($command);
    }

    /**
     * Kirim notifikasi ke layar DAN ke database.
     *
     * Ke database karena hasilnya harus tersimpan: notifikasi "Data Dummy
     * Berhasil Dibuat" dibaca lagi dari menu notifikasi -- termasuk setelah
     * browser dimuat ulang.
     *
     * @param  array<int, \Filament\Notifications\Actions\Action>  $actions
     */
    protected function notify(string $title, string $body, ?array $actions = null, string $status = 'success'): void
    {
        $notification = Notification::make()
            ->{$status}()
            ->title($title)
            ->body($body);

        if ($actions !== null) {
            $notification->actions($actions);
        }

        $notification->send();

        $user = auth()->user();

        if ($user !== null) {
            $notification->sendToDatabase($user);
        }
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

    /**
     * @return array<string, string>
     */
    protected function seederOptions(): array
    {
        $options = [];

        foreach (glob(database_path('seeders/*Seeder.php')) ?: [] as $file) {
            $name = basename($file, '.php');

            $options[$name] = $name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function modelOptions(): array
    {
        $options = [];

        foreach ($this->modelClasses() as $class) {
            $short = class_basename($class);

            $options[$short] = $short;
        }

        return $options;
    }

    /**
     * Semua model di `app/Models`.
     *
     * @return array<int, class-string>
     */
    protected function modelClasses(): array
    {
        $classes = [];

        foreach ($this->phpFilesIn(app_path('Models')) as $file) {
            $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, substr($file, strlen(app_path('Models')) + 1));

            $class = 'App\\Models\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relative
            );

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Cari class model dari nama pendeknya (`Category`).
     *
     * Modelnya berada di sub-namespace (`App\Models\Category\Category`), jadi
     * nama pendek saja tidak cukup untuk membentuk FQCN.
     *
     * @return class-string|null
     */
    protected function resolveModelClass(string $shortName): ?string
    {
        if ($shortName === '') {
            return null;
        }

        foreach ($this->modelClasses() as $class) {
            if (class_basename($class) === $shortName) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Tulis daftar migration ke berkas, lalu kirimkan sebagai unduhan.
     */
    protected function exportMigrations(string $format): mixed
    {
        $migrations = Migration::query()->orderBy('id')->get();

        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filename = 'database-migrations-'.now()->format('Ymd-His').'.'.$format;

        $path = $directory.'/'.$filename;

        if ($format === 'csv') {
            $handle = fopen($path, 'w');

            fputcsv($handle, ['migration', 'batch']);

            foreach ($migrations as $migration) {
                fputcsv($handle, [$migration->migration, $migration->batch]);
            }

            fclose($handle);
        } else {
            file_put_contents($path, (string) json_encode(
                $migrations->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        }

        $this->notify(__('Ekspor Selesai'), __('Berkas disimpan di :path', ['path' => $path]));

        return response()->download($path, $filename);
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
