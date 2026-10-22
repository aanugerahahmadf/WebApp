<?php

namespace App\Filament\Admin\Resources\AdminResource;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\AdminResource\Pages\ManageAdmins\ManageAdmins;
use App\Models\Admin\Admin;
use App\Support\AppIcon\AppIcon;
use App\Support\AdminAccess\AdminAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Manage tim internal -- baris tabel `admins`.
 *
 * Resource ini_exists karena pemisahan tabel tidak cukup sendirian: kalau tidak
 * ada layar untuk membuat baris `admins`, satu-satunya cara menambah admin
 * adalah `php artisan` atau menyunting database, dan biasanya kita berakhir
 * kembali menulis admin ke tabel `users`.
 *
 * Gate-nya memakai `AdminAccess`, sama seperti resource lain -- jadi super
 * admin melihat semua, dan role lain (data_lens, cyber_security, ...)
 * butuh permission `view_admins` / `manage_admins`.
 */
class AdminResource extends Resource
{
    // Hanya untuk `exporterClass()` -- tombol "Ekspor Data" di header
    // halaman memanggilnya lewat `HasStandardActions::standardHeaderActions()`.
    // Aksi barisnya tetap milik table() di bawah, bukan trait ini.
    use HasStandardActions;

    protected static ?string $model = Admin::class;

    protected static ?string $slug = 'admins';

    protected static ?string $navigationIcon = AppIcon::ADMIN_SHIELD;

    protected static ?int $navigationSort = 101;

    /**
     * Grup navigasi: "Data Master" -> "Master Data" (en).
     *
     * WAJIB lewat METHOD, bukan properti mentah.
     *
     * Filament mencocokkan item navigasi ke grup yang terdaftar di panel
     * provider berdasarkan LABEL hasil terjemahan (`__('Data Master')`).
     * Properti mentah 'Data Master' hanya cocok kalau locale-nya Bahasa
     * Indonesia -- di Bahasa Indonesia item ini muncul sebagai grup sendiri
     * ("Data Master") terpisah dari grup "Master Data", jadi SIDEBAR punya
     * dua grup yang isinya sama persis.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    protected static ?string $modelLabel = 'Admin Tim';

    protected static ?string $pluralModelLabel = 'Admin Tim';

    protected static ?string $recordTitleAttribute = 'full_name';

    // â”€â”€ Gate â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //
    // `view_admins` / `manage_admins` DITAMBAHKAN ke seeder
    // RolesAndPermissionsSeeder dan diberi ke cyber_security + super_admin.
    // Tanpa permission ini semua resource lain tetap bisa dibuka, tapi daftar
    // tim internal tidak.
    public static function canViewAny(): bool
    {
        return AdminAccess::allows('view_admins', 'manage_admins');
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return AdminAccess::allows('manage_admins');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return AdminAccess::allows('manage_admins');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Baris super admin tidak boleh dihapus dari panel, karena Dia
        // biasanya satu-satunya yang masih bisa masuk untuk memperbaiki
        // keadaan. Nonaktifkan lewat toggle, jangan dihapus.
        return static::canEdit($record)
            && ! $record->hasRole(AdminAccess::SUPER_ADMIN);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Identitas'))
                ->icon('heroicon-s-identification')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('full_name')
                        ->label(__('Nama Lengkap'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        // Sama seperti di UserResource: nama dipecah otomatis
                        // supaya pencarian & sapaan tidak perlu kolom terpisah
                        // yang harus diisi manual.
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, Forms\Get $get): void {
                            $full = trim((string) $state);
                            $parts = preg_split('/\s+/', $full) ?: [];

                            $set('first_name', $parts[0] ?? null);
                            $set('last_name', count($parts) > 1 ? end($parts) : null);
                            $set('mid_name', count($parts) > 2
                                ? implode(' ', array_slice($parts, 1, -1))
                                : null);
                        }),

                    Forms\Components\TextInput::make('first_name')
                        ->label(__('Nama Depan'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('mid_name')
                        ->label(__('Nama Tengah'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('last_name')
                        ->label(__('Nama Belakang'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('username')
                        ->label(__('Username'))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('Dipakai untuk Sign In di panel admin.')),

                    Forms\Components\TextInput::make('email')
                        ->label(__('Alamat Email'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->label(__('Email Terverifikasi'))
                        ->seconds(false)
                        ->native(false),
                ]),

            Forms\Components\Section::make(__('Keamanan Akun'))
                ->icon('heroicon-s-lock-closed')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->label(__('Kata Sandi'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        // Wajib filled saat edit too kalau field-nya benar-benar
                        // diisi -- kalau tidak, `required` akan menggagalkan
                        // penyimpanan setiap kali admin hanya mau ganti jabatan.
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->confirmed()
                        ->minLength(8)
                        // Cast 'hashed' di model yang benar-benar menghashing,
                        // jadi form tidak perlu Hash::make() sendiri.
                        ->helperText(__('Kosongkan kalau tidak ingin mengganti kata sandi.')),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->label(__('Ulangi Kata Sandi'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(false),

                    Forms\Components\Toggle::make('active_status')
                        ->label(__('Status Akun Aktif'))
                        ->default(true)
                        ->helperText(__('Akun nonaktif tidak bisa masuk, walau kata sandinya benar.'))
                        // Super admin tidak boleh dimatikan: kalau semua admin
                        // terakhir dinonaktifkan, tidak ada yang bisa masuk untuk
                        // menyalakannya lagi (reset lewat CLI harus jadi jalan
                        // keluarnya).
                        ->disabled(fn (?Admin $record): bool => $record?->hasRole(AdminAccess::SUPER_ADMIN) ?? false),

                    Forms\Components\TextInput::make('avatar_url')
                        ->label(__('URL Avatar'))
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://...'),
                ]),

            Forms\Components\Section::make(__('Jabatan'))
                ->icon('heroicon-s-briefcase')
                ->description(__('Jabatan bersifat informatif. Penentu akses tetap role di bawah.'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('position')
                        ->label(__('Jabatan'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('department')
                        ->label(__('Divisi'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('Telepon'))
                        ->tel()
                        ->maxLength(30),

                    Forms\Components\TextInput::make('whatsapp')
                        ->label(__('WhatsApp'))
                        ->tel()
                        ->maxLength(30),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('Catatan Internal'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('Otorisasi'))
                ->icon('heroicon-s-key')
                ->description(__('Role menentukan apa yang terlihat di panel ini. super_admin melihat semuanya.'))
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->searchable()
                        ->label(__('Role'))
                        ->relationship('roles', 'name')
                        // Hanya role ber-guard 'admin'. Role shopper ('user',
                        // 'customer') tidak boleh muncul di sini -- dan kalau
                        // muncul pun, `assignRole()` akan menolaknya, karena
                        // guard model ini adalah 'admin'.
                        ->modifyQueryUsing(fn ($query) => $query->where('guard_name', 'admin'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->headline())
                        ->multiple()
                        ->preload()
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Pagination DIHILANGKAN di panel admin (semua tabel admin
            //_show semua baris_). Resource ini sebelumnya tidak
            // mendeklarasikan `->paginated(...)` sama sekali, jadi Filament
            // memakai bawaan 10 baris per halaman -- itu harus dihentikan
            // juga, kalau tidak tabel admin ini tetap ber-pagination.
            ->paginated(false)
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label(__('Avatar'))
                    ->circular()
                    // `defaultImageUrl()`, bukan `placeholderImageUrl()` --
                    // nama methodnya berbeda di Filament yang terpasang, dan
                    // memanggil yang tidak ada berakhir dengan
                    // "BadMethodCallException" saat tabel dirender.
                    ->defaultImageUrl(url('images/placeholder-avatar.png')),

                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Nama'))
                    ->searchable(['full_name', 'first_name', 'last_name'])
                    ->sortable()
                    ->description(fn (Admin $record): ?string => $record->position),

                Tables\Columns\TextColumn::make('username')
                    ->label(__('Username'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('department')
                    ->label(__('Divisi'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->color(fn (string $state): string => $state === AdminAccess::SUPER_ADMIN
                        ? 'danger'
                        : 'info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Masuk Terakhir'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('â€”'),

                Tables\Columns\TextColumn::make('last_login_ip')
                    ->label(__('IP'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('â€”'),

                Tables\Columns\ToggleColumn::make('active_status')
                    ->label(__('Aktif'))
                    // Sama seperti form: super admin tidak boleh dimatikan lewat
                    // klik di tabel, bukan cuma lewat form.
                    ->disabled(fn (Admin $record): bool => $record->hasRole(AdminAccess::SUPER_ADMIN)),
            ])
            ->filters([
                // `relationship()` dengan closure KETIGA, bukan
                // `->modifyQueryUsing()`.
                //
                // `modifyQueryUsing()` milik filter berlaku untuk query UTAMA
                // tabel -- yaitu tabel `admins`. Karena itu
                // `where('guard_name', 'admin')` masuk ke query admins, dan
                // kolom `guard_name` hanya ada di tabel `roles`. Di MySQL itu
                // error; di SQLite identifier yang tidak dikenal dianggap
                // string literal, jadi hasil_FILTER-nya 0 baris -- tabel
                // admin terlihat KOSONG tanpa error apa pun.
                //
                // Yang dimaksud: pilihan Role di dropdown hanya berisi role
                // ber-guard `admin` (role untuk shopper punya guard `web`, dan
                // ikut tampil di sini kalau tidak dibatasi).
                Tables\Filters\SelectFilter::make('roles')
                    ->label(__('Role'))
                    ->relationship('roles', 'name', fn ($query) => $query->where('guard_name', 'admin'))
                    // `searchable()` wajib: daftar role admin tidak sedikit,
                    // dan filter yang hanya bisa diklik memaksa orang
                    // menggulir untuk mencari satu role.
                    ->searchable(['name']),

                Tables\Filters\TernaryFilter::make('active_status')
                    ->label(__('Status Akun')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('Ubah')),
                Tables\Actions\ViewAction::make()
                    ->label(__('Lihat')),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    // Sama seperti canDelete(): baris super admin dilindungi
                    // di dua tempat supaya pintunya tidak bisa dilewati dengan
                    // memilih row lalu menekan Delete.
                    ->visible(fn (Admin $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('Hapus yang Dipilih'))
                        // Memblokir penghapusan massal yang menyasar super
                        // admin. BulkAction tidak lewat canDelete() per baris,
                        // jadi harus dijaga sendiri di sini.
                        ->before(fn (\Illuminate\Support\Collection $records) => $records
                            ->reject(fn (Admin $record): bool => $record->hasRole(AdminAccess::SUPER_ADMIN))),
                ]),
            ])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAdmins\ManageAdmins::route('/'),
        ];
    }
}
