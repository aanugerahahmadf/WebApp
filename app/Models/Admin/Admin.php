<?php

namespace App\Models\Admin;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use App\Support\AvatarUrl\AvatarUrl;
use App\Support\AdminAccess\AdminAccess;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Identitas tim internal. Berdiri sendiri di atas tabel `admins`, TIDAK
 * mewarisi `App\Models\User\User` dan tidak berbagi satu baris pun dengan
 * identitas belanja.
 *
 * Kenapa bukan satu model dengan role:
 *
 *   Pemisahan guard adalah pemisahan otentikasi, bukan cuma label. Akun pada
 *   tabel `users` autenticates lewat guard `web` dan token Sanctum; akun pada
 *   tabel ini lewat guard `admin` saja. Karena keduanya provider yang berbeda,
 *   `Auth::guard('admin')->attempt(['username' => 'superadmin', ...])` tidak
 *   mungkin terakumulasi di baris mana pun di `users`, dan sebaliknya -- tidak ada
 *   satu baris pun yang bisa di-login lewat dua pintu sekaligus.
 *
 *   Bedanya bukan cuma role, tapi tabel: satu tabel `users` dengan kolom
 *   kolom `is_admin` akan menyisakan pintu yang menyalahgunakan
 *   `impersonate`, dan panel admin akan bisa membaca KYC/KTP user.
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $first_name
 * @property string|null $mid_name
 * @property string|null $last_name
 * @property string $username
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $avatar_url
 * @property string|null $avatar
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $position
 * @property string|null $department
 * @property string|null $notes
 * @property bool $active_status
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_login_user_agent
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\User\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 */
class Admin extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * Guard milik role Spatie untuk model ini.
     *
     * WAJIB 'admin', bukan default 'web'. Inilah yang membuat pemisahan tabel
     * memisahkan tabel secara total: role `super_admin` dibuat dengan
     * `guard_name = 'admin'`,
     * sehingga `User::hasRole('super_admin')` -- yang selalu mencari role
     * ber-guard 'web' -- tidak akan pernah menemukannya pada akun belanja.
     * Tanpa baris ini, memindahkan role ke tabel `admins` tidak akan
     * mengunci apa pun: role-nya masih bisa diberikan ke user biasa.
     */
    protected string $guard_name = 'admin';

    /**
     * Role yang boleh masuk ke panel admin.
     *
     * @var list<string>
     */
    public const ADMIN_ROLES = AdminAccess::ADMIN_ROLES;

    /**
     * Role tim internal yang boleh masuk ke panel USER / WELCOME dan mobile app.
     *
     * Semua akun admin aktif dapat sign-in ke panel storefront dan mobile app.
     * Baris `users` milik admin dibuat/ditautkan secara otomatis lewat
     * `ensureStorefrontIdentity()` -- baik di SignIn panel user maupun di API
     * login mobile app.
     *
     * @var list<string>
     */
    public const STOREFRONT_ROLES = self::ADMIN_ROLES;

    /**
     * Eager-load relasi role.
     *
     * Wajib: `hasRole()` Spatie memanggil relasi `roles` di setiap
     * pemeriksaan, dan panel admin memanggilnya berkali-kali per render
     * (middleware, canAccessPanel, item navigasi, Gate::before). Tanpa ini
     * satu request bisa menembak query yang sama puluhan kali.
     */
    protected $with = ['roles'];

    protected $fillable = [
        'full_name',
        'first_name',
        'mid_name',
        'last_name',
        'username',
        'email',
        'password',
        'avatar_url',
        'avatar',
        'phone',
        'whatsapp',
        'position',
        'department',
        'notes',
        'active_status',
        'user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast wajib `hashed`: baris `admins` diisi lewat seeder dengan
     * `Hash::make(...)`, dan kolomnya tetap menyimpan hash. Tanpa cast ini,
     * `Admin::create(['password' => '...'])` akan menyimpan teks polos.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'active_status' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ── Kontrak Filament ───────────────────────────────────────────

    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->username ?? 'Admin';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        // Sudah diterjemahkan oleh accessor di bawah.
        return $this->avatar_url;
    }

    /**
     * URL avatar untuk ditampilkan.
     *
     * Aturan penerjemahannya (path relatif, URL absolut ke host ini, URL
     * pihak ketiga) ada di App\Support\AvatarUrl\AvatarUrl -- satu tempat
     * supaya User dan Admin tidak bisa berbeda pendapat.
     *
     * Yang menulis ke kolom ini harus memakai
     * `getRawOriginal('avatar_url')` -- lihat syncStorefrontAvatar() dan
     * ensureStorefrontIdentity().
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => AvatarUrl::for($value),
        );
    }

    public function getName(): string
    {
        return $this->getFilamentName();
    }

    // ── Otorisasi ──────────────────────────────────────────────────

    /**
     * Pintu masuk per panel.
     *
     * Admin aktif boleh masuk ke panel admin (guard 'admin') DAN panel user
     * (guard 'web' via identitas belanja yang di-grant otomatis lewat
     * ensureStorefrontIdentity()). Panel welcome publik tidak butuh login.
     *
     * Semua role admin (ADMIN_ROLES) termasuk STOREFRONT_ROLES, jadi setiap
     * admin aktif bisa mengakses panel user dan mobile app.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = $panel->getId();

        if ($panelId === 'admin') {
            return $this->active_status
                && $this->hasAnyRole(AdminAccess::ADMIN_ROLES);
        }

        if ($panelId === 'user' || $panelId === 'welcome') {
            // Semua admin aktif boleh akses panel user & mobile app.
            // Identitas belanja (users) dibuat/ditautkan otomatis saat login
            // lewat ensureStorefrontIdentity() -- baik di SignIn panel user
            // maupun di API login mobile app.
            return (bool) $this->active_status;
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Alias kompatibilitas untuk `$record->is_admin`.
     *
     * Dipakai view blade admin (mis. lencana role di header) yang dulu
     * membaca atribut ini dari model User.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Otentikasi hanya lewat guard `admin`.
     *
     * Sengaja tidak ada `HasApiTokens`: model ini tidak pernah menerbitkan
     * token Sanctum, jadi tidak ada jalan untuk memakainya sebagai bearer
     * token di API HP.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    // ── Relasi ─────────────────────────────────────────────────────

    /**
     * Identitas belanja yang di-grant lewat `admin:grant-storefront`.
     * Null kalau admin ini memang tidak punya, dan itu kondisi default.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User\User::class, 'user_id');
    }

    /**
     * Rekam jejak masuk terakhir, untuk audit panel admin.
     */
    public function touchLastLogin(?string $ip = null, ?string $userAgent = null): void
    {
        $this->forceFill(array_filter([
            'last_login_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
            'last_login_user_agent' => $userAgent
                ?? substr((string) request()->userAgent(), 0, 255),
        ]))->saveQuietly();
    }

    /**
     * Menolak akun nonaktif.
     *
     * Dipanggil dari middleware guard `admin` supaya akun yang dinonaktifkan
     * langsung kehilangan akses tanpa harus menunggu token Sanctum/kunci
     * session-nya kedaluwarsa.
     */
    public function isActive(): bool
    {
        return (bool) $this->active_status;
    }

    /**
     * Pastikan admin ini punya identitas belanja yang tertaut, dengan kata
     * sandi yang sama dengan kredensial admin. Dipakai supaya admin bisa
     * masuk lewat halaman Sign-In panel user DAN mobile app memakai kredensial
     * admin-nya. Identitas belanja dibuat/diperbarui otomatis saat login.
     */
    public function ensureStorefrontIdentity(string $password): \App\Models\User\User
    {
        if ($this->user()->exists()) {
            $user = $this->user;
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // yang akan hash otomatis. JANGAN Hash::make di sini (double-hash).
            // 2FA TIDAK PERNAH aktif untuk admin team — dimatikan eksplisit.
            $user->forceFill([
                'username' => $this->storefrontUsername($user),
                'password' => $password,
                'active_status' => true,
                'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
                'two_factor_enabled' => false,
            ])->save();

            $this->syncStorefrontAvatar($user);

            return $user;
        }

        $existing = \App\Models\User\User::where('email', $this->email)->first();

        if ($existing) {
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            // 2FA TIDAK PERNAH aktif untuk admin team.
            $existing->forceFill([
                'username' => $this->storefrontUsername($existing),
                'password' => $password,
                'active_status' => true,
                'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
                'two_factor_enabled' => false,
            ])->save();

            $this->forceFill(['user_id' => $existing->id])->save();

            $this->syncStorefrontAvatar($existing);

            return $existing;
        }

        $candidate = $this->storefrontUsername();

        $user = \App\Models\User\User::create([
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'mid_name' => $this->mid_name,
            'last_name' => $this->last_name,
            'username' => $candidate,
            'email' => $this->email,
            // Foto yang sama dengan akun admin. Tanpa ini baris users hanya
            // memakai placeholder `avatar.png` yang biasanya tidak ada
            // filenya, sehingga topbar storefront menampilkan inisial saja
            // ("SA") padahal admin sudah punya foto.
            //
            // `getRawOriginal`, BUKAN `$this->avatar_url`: accessor avatarUrl()
            // menormalkan URL ke origin request yang sedang berjalan, dan
            // nilai yang Welcome itu yang harus tersimpan -- kalau bukan, kolom
            // berisi `http://127.0.0.1:8000/...` dan rusak di server lain.
            'avatar_url' => $this->getRawOriginal('avatar_url'),
            // Password MENTAH — model User punya cast 'password' => 'hashed'
            'password' => $password,
            'email_verified_at' => now(),
            'active_status' => true,
            'occupation' => $this->position ?: 'Admin',
            // 2FA TIDAK PERNAH aktif untuk admin team.
            'two_factor_type' => \MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType::None->value,
            'two_factor_enabled' => false,
        ]);

        $this->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    /**
     * The linked storefront identity uses the admin username as-is. The old
     * `.staff` suffix made the profile show `superadmin.staff`, even though
     * the canonical admin login is `superadmin`.
     */
    private function storefrontUsername(?\App\Models\User\User $current = null): string
    {
        $base = \Illuminate\Support\Str::slug((string) $this->username, '.') ?: 'staff';
        $candidate = $base;
        $suffix = 2;

        while (\App\Models\User\User::where('username', $candidate)
            ->when($current, fn ($query) => $query->where('id', '!=', $current->getKey()))
            ->exists()) {
            $candidate = $base.'.'.$suffix++;
        }

        return $candidate;
    }

    /**
     * Sinkronkan foto admin ke identitas belanja yang tertaut.
     *
     * Dipanggil setiap login admin (ensureStorefrontIdentity). Aturannya:
     *
     *   - Admin tidak punya `avatar_url`       -> tidak diapa-apakan.
     *   - Users punya unggahan sendiri         -> JANGAN ditimpa. Unggahan
     *     dari storefront/app disimpan sebagai PATH RELATIF (`avatars/...`)
     *     oleh FileUpload/StorageService; inilah penandanya.
     *   - Users belum punya / masih berisi URL -> disamakan dengan foto admin
     *     (URL penuh), sehingga kalau admin ganti foto, storefront ikut.
     */
    private function syncStorefrontAvatar(\App\Models\User\User $user): void
    {
        // Semua perbandingan dan penulisan memakai NILAI MENTAH.
        //
        // Accessor avatarUrl() menormalkan URL ke origin request yang sedang
        // berjalan. Kalau nilai itu ikut dipakai di sini, kolom `users`
        // akan tersimpan `http://127.0.0.1:8000/media/...` -- benar di
        // mesin ini, rusak di server lain. Yang harus tersimpan adalah nilai
        // yang ada di database.
        $adminAvatar = $this->getRawOriginal('avatar_url');

        if (blank($adminAvatar)) {
            return;
        }

        $raw = $user->getRawOriginal('avatar_url');

        if (filled($raw) && ! filter_var($raw, FILTER_VALIDATE_URL)) {
            return;
        }

        if ($raw === $adminAvatar) {
            return;
        }

        $user->forceFill(['avatar_url' => $adminAvatar])->save();
    }
}
