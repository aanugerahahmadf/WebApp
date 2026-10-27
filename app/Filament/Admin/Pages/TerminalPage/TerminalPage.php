<?php

namespace App\Filament\Admin\Pages\TerminalPage;

use App\Support\AppIcon\AppIcon;
use App\Support\AdminAccess\AdminAccess;
use App\Support\Terminal\ArtisanCommandInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Terminal admin -- menjalankan perintah Artisan dari panel.
 *
 * PERINTAH YANG DIBLOKIR TIDAK PERNAH BISA DIJALANKAN. Panel admin sudah
 * punya hak akses penuh ke data, jadi daftar putihnya harus sempit:
 * perintah yang bisa menghapus atau membocorkan (tinker, env, key:generate)
 * dikecualikan, bukan cuma disembunyikan.
 *
 * Perintah yang ditolak TIDAK menghasilkan output apa pun: `hasExecuted`
 * tetap false, jadi tidak ada yang mengira perintah itu jalan.
 */
class TerminalPage extends Page
{

    protected static string $view = 'Admin.pages.terminal-page.terminal-page';

    protected static ?string $navigationIcon = AppIcon::CODE;

    protected static ?int $navigationSort = 206;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('manage_system_configuration', 'manage_database');
    }

    /**
     * State form.
     *
     * WAJIB dideklarasikan (bukan dynamic property): Livewire hanya
     * menyertakan properti yang dideklarasikan saat menyiapkan data
     * validasi. Kalau `data` tidak ada, aturan validasi form tidak punya
     * pasangan dan Livewire melempar "No property found for validation".
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $commandOutput = '';

    public ?int $exitCode = null;

    public bool $hasExecuted = false;

    public string $lastCommand = '';

    /**
     * Riwayat perintah yang sudah dijalankan.
     *
     * @var array<int, array{command: string, output: string, success: bool, exit_code: int|null, timestamp: string}>
     */
    public array $commandHistory = [];

    /**
     * Perintah yang boleh dijalankan.
     *
     * @var array<int, string>
     */
    public const ALLOWED_COMMANDS = [
        'about',
        'cache:clear',
        'config:clear',
        'config:cache',
        'db:seed',
        'db:show',
        'migrate',
        'migrate:status',
        'migrate:rollback',
        'optimize:clear',
        'route:clear',
        'route:list',
        'storage:link',
        'sync',
        'view:clear',
        'view:cache',
    ];

    /**
     * Perintah yang SELALU ditolak, apa pun keadaannya.
     *
     * @var array<int, string>
     */
    public const BLOCKED_COMMANDS = [
        'tinker',
        'serve',
        'env',
        'key:generate',
        'db:wipe',
        'migrate:fresh',
        'migrate:reset',
        'storage:unlink',
    ];

    public function getTitle(): string
    {
        return __('Terminal');
    }

    /**
     * Slug halaman: `terminal`, bukan `terminal-page`.
     *
     * Filament menurunkan slug dari nama class, jadi tanpa override ini
     * URL-nya jadi `/admin/terminal-page`.
     */
    public static function getSlug(): string
    {
        return 'terminal';
    }

    public static function getNavigationLabel(): string
    {
        return __('Terminal');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
    }

        public function getBreadcrumbs(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('quick_command')
                    ->searchable()
                    ->label(__('Perintah Cepat'))
                    ->options($this->quickCommandOptions())
                    ->placeholder(__('Pilih perintah cepat'))
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        if (filled($state)) {
                            // `fill()` MENGGANTIKAN seluruh state form. Kalau
                            // `quick_command` tidak ikut diisi, key itu hilang
                            // dari state sementara aturan validasinya masih
                            // ada -> "No property found for validation".
                            $this->form->fill([
                                'quick_command' => $state,
                                'command' => $state,
                            ]);
                        }
                    }),
                TextInput::make('command')
                    ->label(__('Perintah Artisan'))
                    ->placeholder('migrate:status')
                    ->required(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    protected function quickCommandOptions(): array
    {
        return [
            'migrate:status' => __('Status Migrasi'),
            'migrate' => __('Jalankan Migrasi'),
            'db:show' => __('Info Database'),
            'route:list' => __('Daftar Route'),
            'config:clear' => __('Bersihkan Cache Config'),
            'optimize:clear' => __('Bersihkan Cache Aplikasi'),
            'sync' => __('Sinkron'),
        ];
    }

    /**
     * Jalankan perintah Artisan.
     */
    public function executeCommand(): void
    {
        // Admin mengetik seperti di shell: "php artisan migrate". Tanpa
        // normalisasi, Artisan akan mencari command bernama "php".
        $command = ArtisanCommandInput::normalize((string) ($this->form->getState()['command'] ?? ''));

        if ($command === '' || ! $this->isAllowed($command)) {
            // Perintah yang ditolak tidak menghasilkan apa pun: `hasExecuted`
            // tetap false dan tidak ada output yang menyesatkan.
            return;
        }

        $output = new BufferedOutput;

        try {
            $exitCode = (int) Artisan::call($command, [], $output);

            $this->commandOutput = $output->fetch();
        } catch (\Throwable $e) {
            // Perintah yang tidak dikenal (atau yang gagal di tengah jalan)
            // harus TAMPIL sebagai pesan -- bukan sebagai halaman kosong.
            $this->commandOutput = $output->fetch().$e->getMessage();
            $exitCode = 1;
        }

        $this->exitCode = $exitCode;
        $this->hasExecuted = true;
        $this->lastCommand = $command;

        $this->commandHistory[] = [
            'command' => $command,
            'output' => $this->commandOutput,
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Kosongkan riwayat perintah.
     */
    public function clearHistory(): void
    {
        $this->commandHistory = [];
    }

    /**
     * Jalankan ulang perintah dari riwayat.
     */
    public function rerunFromHistory(int $index): void
    {
        $entry = $this->commandHistory[$index] ?? null;

        if (! is_array($entry)) {
            return;
        }

        $this->form->fill(['command' => $entry['command']]);

        $this->executeCommand();
    }

    /**
     * Bersihkan output dan status eksekusi.
     */
    public function clearOutput(): void
    {
        $this->commandOutput = '';
        $this->exitCode = null;
        $this->hasExecuted = false;
        $this->lastCommand = '';
    }

    /**
     * Apakah perintah ini boleh dijalankan?
     */
    /**
     * Apakah perintah ini boleh dijalankan?
     *
     * Hanya perintah yang DITOLAK yang tidak boleh. Perintah lain
     * dicoba -- kalau tidak dikenal, Artisan yang jawab ("The command ...
     * does not exist"), dan itu informasi yang berguna buat admin.
     */
    protected function isAllowed(string $command): bool
    {
        $name = ArtisanCommandInput::commandName($command);

        if ($name === '') {
            return false;
        }

        return ! in_array($name, self::BLOCKED_COMMANDS, true);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedCommands(): array
    {
        return self::ALLOWED_COMMANDS;
    }
}
