<?php

namespace App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ListDeveloperStudio;

use App\Filament\Admin\Exports\DeveloperStudioEndpointExporter\DeveloperStudioEndpointExporter;
use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use App\Filament\Concerns\InteractsWithGraphQLAndGrpc\InteractsWithGraphQLAndGrpc;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Models\DeveloperStudio\DeveloperStudioEndpoint;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

/**
 * Halaman daftar endpoint Studio API.
 *
 * Aksinya BUKAN standar Filament (Tambah/Ekspor), karena halaman ini bukan
 * sekadar daftar data: dia juga menjalankan sinkronisasi, membersihkan cache,
 * dan menguji GraphQL/gRPC.
 */
class ListDeveloperStudio extends \Filament\Resources\Pages\ListRecords
{
    use HasStandardActions;

    use InteractsWithGraphQLAndGrpc;

    protected static string $resource = DeveloperStudioResource::class;

    /**
     * Tab per jenis endpoint, dengan angka jumlahnya.
     *
     * Kenapa tab, bukan hanya filter: 600+ endpoint bercampur (route web,
     * REST API, controller, GraphQL, gRPC, Capacitor, Flutter, Python) sulit
     * dibaca sebagai satu daftar. Tab membuat "saya mau lihat yang mana"
     * menjadi satu klik, dan angkanya memberi tahu isi masing-masing jenis
     * tanpa harus membuka tabnya.
     *
     * Angkanya diambil SATU query agregat (bukan satu query per tab).
     */
    public function getTabs(): array
    {
        $counts = DeveloperStudioEndpoint::query()
            ->selectRaw('type, count(*) as total')
            ->whereNotNull('type')
            ->groupBy('type')
            ->pluck('total', 'type');

        $tabs = [
            Tab::make(__('Semua'))
                ->badge((int) $counts->sum()),
        ];

        foreach (['api', 'web', 'admin', 'controller', 'graphql', 'grpc', 'capacitor', 'flutter', 'python'] as $type) {
            $tabs[$type] = Tab::make(DeveloperStudioResource::typeLabel($type))
                ->badge((int) ($counts[$type] ?? 0))
                ->modifyQueryUsing(fn ($query) => $query->where('type', $type));
        }

        return $tabs;
    }

    /**
     * Aksi di HEADER TABEL (bukan header halaman).
     *
     * Kenapa di header tabel: aksi-aksinya relating ke TABEL yang ada di
     * bawahnya (sinkronkan endpoint, uji GraphQL/gRPC, cache, ekspor).
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
            Action::make('create_new')
                ->label(__('Buat Endpoint Baru'))
                ->icon('/images/icons/plus.svg')
                ->url(DeveloperStudioResource::getUrl('create')),

            ExportAction::make()
                ->exporter(DeveloperStudioEndpointExporter::class)
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
                    Action::make('sync_now')
                        ->label(__('Sinkronkan Sekarang'))
                        ->icon('/images/icons/refresh.svg')
                        ->color('primary')
                        ->action(function (): void {
                            $count = DeveloperStudioEndpoint::syncAll();

                            Notification::make()
                                ->success()
                                ->title(__('Sinkronisasi Selesai'))
                                ->body(__('Endpoint yang ditemukan: :count', ['count' => $count]))
                                ->send();
                        }),

                    Action::make('run_graphql_query')
                        ->label(__('Jalankan GraphQL Query'))
                        ->icon('/images/icons/code.svg')
                        ->form([
                            Textarea::make('query')
                                ->label(__('Query'))
                                ->required()
                                ->default('{ __typename }')
                                ->rows(5),
                            Textarea::make('variables')
                                ->label(__('Variables (JSON)'))
                                ->default('{}')
                                ->rows(3),
                        ])
                        ->action(function (array $data): void {
                            $variables = json_decode((string) ($data['variables'] ?? '{}'), true);

                            if (! is_array($variables)) {
                                $variables = [];
                            }

                            $result = $this->executeGraphQL((string) ($data['query'] ?? ''), $variables);

                            $hasError = ! empty($result['errors']);

                            Notification::make()
                                ->{$hasError ? 'danger' : 'success'}()
                                ->title($hasError ? __('GraphQL Gagal') : __('GraphQL Berhasil'))
                                ->body((string) json_encode($result['data'] ?? $result['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                ->send();
                        }),

                    Action::make('check_grpc_status')
                        ->label(__('Cek Status gRPC'))
                        ->icon('/images/icons/server.svg')
                        ->action(function (): void {
                            $health = static::checkApiEnginesHealth();

                            Notification::make()
                                ->success()
                                ->title(__('Status API Engine'))
                                ->body(__('GraphQL: :graphql | gRPC: :grpc | Overall: :overall', [
                                    'graphql' => $health['graphql']['status'] ?? '-',
                                    'grpc' => $health['grpc']['status'] ?? '-',
                                    'overall' => $health['overall'] ?? '-',
                                ]))
                                ->send();
                        }),
                ])
                ->label(__('Uji API'))
                ->icon('/images/icons/code.svg')
                ->groupedIcon('/images/icons/code.svg')
                ->color('primary'),

            ActionGroup::make([
                    Action::make('route_cache')
                        ->label(__('Cache Route'))
                        ->icon('/images/icons/bolt.svg')
                        ->action(fn (): null => $this->runArtisan('route:cache')),

                    Action::make('route_clear')
                        ->label(__('Hapus Cache Route'))
                        ->icon('/images/icons/x.svg')
                        ->action(fn (): null => $this->runArtisan('route:clear')),

                    Action::make('optimize_clear')
                        ->label(__('Bersihkan Cache Optimasi'))
                        ->icon('/images/icons/refresh.svg')
                        ->action(fn (): null => $this->runArtisan('optimize:clear')),
                ])
                ->label(__('Cache & Perawatan'))
                ->icon('/images/icons/bolt.svg')
                ->groupedIcon('/images/icons/bolt.svg')
                ->color('gray'),

            ActionGroup::make([
                    Action::make('export_json')
                        ->label(__('Ekspor JSON'))
                        ->icon('/images/icons/download.svg')
                        ->color('success')
                        ->action(fn (): mixed => $this->exportEndpoints('json')),

                    Action::make('export_csv')
                        ->label(__('Ekspor CSV'))
                        ->icon('/images/icons/download.svg')
                        ->color('success')
                        ->action(fn (): mixed => $this->exportEndpoints('csv')),
                ])
                ->label(__('Ekspor'))
                ->icon('/images/icons/download.svg')
                ->groupedIcon('/images/icons/download.svg')
                ->color('success'),
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
     * Jalankan perintah Artisan lalu laporkan hasilnya.
     */
    protected function runArtisan(string $command): null
    {
        Artisan::call($command);

        Notification::make()
            ->success()
            ->title(__('Perintah Selesai: :command', ['command' => $command]))
            ->body((string) Artisan::output())
            ->send();

        return null;
    }

    /**
     * Tulis daftar endpoint ke berkas, lalu kirimkan sebagai unduhan.
     */
    protected function exportEndpoints(string $format): mixed
    {
        $endpoints = DeveloperStudioEndpoint::query()->get();

        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filename = 'developer-studio-endpoints-'.now()->format('Ymd-His').'.'.$format;

        $path = $directory.'/'.$filename;

        if ($format === 'csv') {
            $handle = fopen($path, 'w');

            fputcsv($handle, ['type', 'method', 'uri', 'name', 'action', 'status']);

            foreach ($endpoints as $endpoint) {
                fputcsv($handle, [
                    $endpoint->type,
                    $endpoint->method,
                    $endpoint->uri,
                    $endpoint->name,
                    $endpoint->action,
                    $endpoint->status,
                ]);
            }

            fclose($handle);
        } else {
            file_put_contents($path, (string) json_encode(
                $endpoints->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        }

        Notification::make()
            ->success()
            ->title(__('Ekspor Selesai'))
            ->body(__('Berkas disimpan di :path', ['path' => $path]))
            ->send();

        return response()->download($path, $filename);
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
