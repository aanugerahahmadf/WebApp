<?php

namespace App\Models\User;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\BackupCode\BackupCode;
use App\Models\Order\Order;
use App\Models\Report\Report;
use App\Models\Transaction\Transaction;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\UserFcmDevice\UserFcmDevice;
use App\Models\UserSession\UserSession;
use App\Models\Voucher\Voucher;
use App\Models\Wishlist\Wishlist;

use App\Models\Traits\HasFilamentMessages\HasFilamentMessages;
use App\Traits\InteractsWithLanguages\InteractsWithLanguages;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravolt\Indonesia\Models\City as IndonesiaCity;
use Laravolt\Indonesia\Models\District as IndonesiaDistrict;
use Laravolt\Indonesia\Models\Province as IndonesiaProvince;
use Laravolt\Indonesia\Models\Village as IndonesiaVillage;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
    use MixCode\FilamentMulti2fa\Enums\TwoFactorAuthType;
    use MixCode\FilamentMulti2fa\Traits\UsingTwoFA;

/**
 * @property int $id
 * @property string $full_name
 * @property string $email
 * @property float $balance
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $first_name
 * @property string|null $mid_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string|null $avatar_url
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $ip_address
 * @property string|null $login_city
 * @property string|null $login_region
 * @property string|null $login_country
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $budget
 * @property Carbon|null $wedding_date
 * @property string|null $theme_preference
 * @property string|null $color_preference
 * @property string|null $event_concept
 * @property string|null $dream_venue
 * @property string|null $custom_fields
 * @property bool $active_status
 * @property string $avatar
 * @property int $dark_mode
 * @property string|null $messenger_color
 * @property-read UserLanguage|null $lang
 * @property-read mixed $name
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, PaymentMethod> $paymentMethods
 * @property-read int|null $payment_methods_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, Wishlist> $wishlists
 * @property-read int|null $wishlists_count
 *
 * @method \Illuminate\Database\Eloquent\Builder allConversations()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereActiveStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBudget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereColorPreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDreamVenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEventConcept($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMidName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMessengerColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereThemePreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWeddingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \App\Models\User\User|null find(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\User\User findOrFail(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\User\User|null first(array|string $columns = ['*'])
 * @method static \App\Models\User\User firstOrFail(array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int, \App\Models\User\User> get(array|string $columns = ['*'])
 *
 * @property string $fullName
 * @property Carbon|null $emailVerifiedAt
 * @property string|null $rememberToken
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 * @property string|null $firstName
 * @property string|null $midName
 * @property string|null $lastName
 * @property string|null $avatarUrl
 * @property Carbon|null $weddingDate
 * @property string|null $themePreference
 * @property string|null $colorPreference
 * @property string|null $eventConcept
 * @property string|null $dreamVenue
 * @property string|null $customFields
 * @property bool $activeStatus
 * @property int $darkMode
 * @property string|null $messengerColor
 * @property-read int|null $notificationsCount
 * @property-read bool|null $notificationsExists
 * @property-read int|null $ordersCount
 * @property-read bool|null $ordersExists
 * @property-read int|null $paymentMethodsCount
 * @property-read bool|null $paymentMethodsExists
 * @property-read int|null $paymentsCount
 * @property-read bool|null $paymentsExists
 * @property-read int|null $permissionsCount
 * @property-read bool|null $permissionsExists
 * @property-read int|null $rolesCount
 * @property-read bool|null $rolesExists
 * @property-read int|null $tokensCount
 * @property-read bool|null $tokensExists
 * @property-read int|null $wishlistsCount
 * @property-read bool|null $wishlistsExists
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAvatar, HasName, MustVerifyEmail
{
    use LegacyMorphClass;
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasFilamentMessages;
    use HasRoles;
    use InteractsWithLanguages;
    use Notifiable;
    // 2FA: Email OTP + Authenticator App + perangkat terpercaya.
    // Memberi generateTwoFactorAuthenticatorAppOTPCode(), verifyOTP(),
    // addTrustedDevice(), hasSetupTwoFactor(), dan relasi trustedDevices()
    // yang menunjuk model milik package.
    use UsingTwoFA;

    public function getFilamentName(): string
    {
        return $this->full_name ?? $this->username ?? 'User';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('super_admin') || $this->hasRole('vendor');
        }

        // 'user' is the account panel, 'welcome' the storefront that leads to it.
        // Same audience: anyone who may browse the storefront may browse the
        // account it fronts, and the storefront additionally admits guests
        // (see AuthenticateWelcome).
        if (in_array($panel->getId(), ['user', 'welcome'], true)) {
            return true;
        }

        return false;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'clerk_id',
        'full_name',
        'first_name',
        'mid_name',
        'last_name',
        'username',
        'email',
        'password',
        'avatar_url',
        'phone',
        'whatsapp',
        'whatsapp_verified_at',
        'ktp_number',
        'passport_number',
        'sim_number',
        'npwp_number',
        'birth_place',
        'birth_date',
        'ktp_photo',
        'selfie_photo',
        'face_scan_photo',
        'country',
        'province_id',
        'city_id',
        'district_id',
        'village_id',
        'province_name',
        'city_name',
        'district_name',
        'village_name',
        'postal_code',
        'identity_type',
        'address',
        'ip_address',
        'login_city',
        'login_region',
        'login_country',
        'latitude',
        'longitude',
        'budget',
        'wedding_date',
        'theme_preference',
        'color_preference',
        'event_concept',
        'dream_venue',
        'active_status',
        'fcm_token',
        'email_verification_token',
        'gender',
        'religion',
        'marital_status',
        'mother_name',
        'occupation',
        'income_range',
        'source_of_funds',
        'social_id',
        'social_type',
        'app_lock_fingerprint_enabled',
        'app_lock_face_enabled',
        'app_lock_pin_enabled',
        'app_lock_pin_hash',
        'app_lock_face_enrolled',
        'app_lock_face_reference',
        'app_lock_face_enrolled_at',
        'app_lock_last_unlock_at',
        'notification_preferences',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'ktp_photo_url',
        'selfie_photo_url',
        'face_scan_photo_url',
    ];

    protected $with = ['roles'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_lock_pin_hash',
        'app_lock_face_reference',
        'email_verification_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Default dua_factor_type.
     *
     * Kolomnya NOT NULL dengan default 'none' di database, tapi default
     * database tidak ikut ke atribut model yang baru dibuat -- sehingga
     * dua_factor_type terbaca NULL pada instance itu. Setting di sini
     * membuat model baru langsung konsisten dengan isi tabel.
     */
    protected $attributes = [
        'two_factor_type' => TwoFactorAuthType::None->value,
    ];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'whatsapp_verified_at' => 'datetime',
            'face_verified_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
            'face_liveness' => 'array',
            'liveness_completed' => 'boolean',
            // Hasil verifikasi dokumen identitas dari AI Core (/api/ktp/verify).
            // Lihat migration 2026_10_05_000001 untuk perbedaan makna
            // doc_ai_reason (nama dokumen) vs doc_ai_reason_code (kode error).
            'doc_ai_verified_at' => 'datetime',
            'doc_ai_blocking_issue' => 'array',
            'doc_ai_score' => 'decimal:2',
            'doc_ai_number_matches_profile' => 'boolean',
            'password' => 'hashed',
            'birth_date' => 'date',
            'wedding_date' => 'date',
            'budget' => 'decimal:2',
            'active_status' => 'boolean',
            'app_lock_fingerprint_enabled' => 'boolean',
            'app_lock_face_enabled' => 'boolean',
            'app_lock_pin_enabled' => 'boolean',
            'app_lock_face_enrolled' => 'boolean',
            'app_lock_face_enrolled_at' => 'datetime',
            'app_lock_last_unlock_at' => 'datetime',
            'notification_preferences' => 'array',
            // Kolom 2FA milik mix-code/filament-multi-2fa. Tanpa cast ini
            // two_factor_type tetap string, dan hasSetupTwoFactor() akan
            // selalu salah membandingkan dengan enum.
            'two_factor_type' => TwoFactorAuthType::class,
            'two_factor_sent_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            // Tanpa cast ini nilainya balik dari database sebagai int 0/1,
            // bukan true/false. twoFactorStatus() meneruskannya apa adanya
            // ke JSON, jadi klien menerima 1 di tempat yang diharapkan bool --
            // dan di Dart itu akan gagal di-decode ke bool.
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function kycReviewer()
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    /**
     * Fallback accessor for packages that expect 'name' attribute.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->full_name,
        );
    }

    public function getAvatarUrlAttribute($value): ?string
    {
        $path = $value ?: $this->attributes['avatar'] ?? null;

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');

        if ($cleanPath === 'avatar.png' && ! file_exists(storage_path('app/public/avatar.png'))) {
            return null;
        }

        return url('media/'.$cleanPath);
    }

    public function getKtpPhotoUrlAttribute($value): ?string
    {
        $path = $this->attributes['ktp_photo'] ?? null;

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');

        return url('media/'.$cleanPath);
    }

    public function getSelfiePhotoUrlAttribute($value): ?string
    {
        $path = $this->attributes['selfie_photo'] ?? null;

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');

        return url('media/'.$cleanPath);
    }

    public function getFaceScanPhotoUrlAttribute($value): ?string
    {
        $path = $this->attributes['face_scan_photo'] ?? null;

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('storage/', '', $path), '/');

        return url('media/'.$cleanPath);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers')
            ->withPivot('claimed_at', 'used_at', 'order_id')
            ->withTimestamps();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(IndonesiaProvince::class, 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(IndonesiaCity::class, 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(IndonesiaDistrict::class, 'district_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(IndonesiaVillage::class, 'village_id');
    }

    public function fcmDevices()
    {
        return $this->hasMany(UserFcmDevice::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    /*
     * trustedDevices() sengaja dihapus: trait UsingTwoFA sudah mendefinisikan
     * relasi yang sama, menunjuk model milik package. Versi lama menunjuk
     * App\Models\TrustedDevice dengan kolom device_fingerprint / platform /
     * trusted_at, sedangkan tabel trust_devices sekarang memakai
     * device_signature / expires_at.
     */

    /**
     * Versi null-safe dari hasSetupTwoFactor() milik trait UsingTwoFA.
     *
     * Yang dari package menulis $this->two_factor_type->value tanpa
     * penjaga null, padahal CheckTrustedDevice memanggilnya di setiap
     * request panel terautentikasi. Satu barik dengan NULL akan membuat
     * seluruh panel 500 untuk user tersebut, bukan hanya halaman 2FA.
     */
    public function hasSetupTwoFactor(): bool
    {
        return $this->two_factor_type !== null
            && $this->two_factor_type->value !== TwoFactorAuthType::None->value;
    }

    public function backupCodes()
    {
        return $this->hasMany(BackupCode::class);
    }

    /**
     * Buang semua perangkat yang sedang masuk KECUALI yang sedang dipakai
     * request ini, dan kembalikan berapa yang dibuang.
     *
     * Padanan "Logout dari perangkat lain" pada
     * HandlesPasswordSecurity::updatePassword() di panel Filament, tapi
     * ditulis ulang untuk dua sumber sekaligus:
     *
     *   - token Sanctum: setiap aplikasi/Perangkat yang "Ingat Saya" adalah
     *     satu token, jadi inilah yang diper comprehensively. Hanya sesi
     *     database yang dihapus berarti perangkat mobile yang login
     *     sebelumnya tetap punya akses -- dan perangkat mobile adalah
     *     majority di sini, bukan pengecualian.
     *   - baris session database: browser web.
     *
     * Pengecualiannya penting dan tidak boleh dihapus: token dan session
     * milik request yang SEDANG berjalan ikut terbuang kalau tidak, dan
     * pemanggil akan langsung logout di detik yang sama dengan mengetik
     * kata sandi barunya.
     */
    public function revokeOtherSessions(): int
    {
        $currentTokenId = $this->currentAccessToken()?->getKey();

        $revokedTokens = $this->tokens()
            ->when(
                $currentTokenId !== null,
                fn ($query) => $query->where('id', '!=', $currentTokenId),
            )
            ->delete();

        $revokedSessions = 0;

        // Hanya berlaku kalau driver session memakai database. Untuk driver
        // lain tidak ada tabel yang bisa dihapus, dan mengarang-hapus lewat
        // tabel yang tidak dipakai akan merusak data driver itu -- jadi lebih
        // baik melaporkan tidak ada yang dihapus.
        if (config('session.driver') === 'database') {
            $revokedSessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->where('id', '!=', request()->session()->getId())
                ->delete();
        }

        return $revokedTokens + $revokedSessions;
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->roles->contains('name', 'super_admin');
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    public function isProfileComplete(): bool
    {
        $required = [
            'first_name',
            'mid_name',
            'last_name',
            'whatsapp',
            'gender',
            'address',
            'occupation',
            'identity_type',
        ];

        foreach ($required as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Complete Profile hanya untuk akun Google (social) yang belum lengkap.
     * Akun form (Sign Up / Sign In) datanya sudah sama, langsung ke home.
     */
    public function shouldCompleteProfile(): bool
    {
        return filled($this->social_type) && ! $this->isProfileComplete();
    }
}
