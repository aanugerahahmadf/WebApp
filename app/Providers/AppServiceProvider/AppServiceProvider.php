<?php

namespace App\Providers\AppServiceProvider;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\AppIcon\VendorIconAliases;
use App\Filament\Admin\Auth\SignIn\SignIn as AdminSignIn;
use App\Filament\Admin\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt as AdminOtpEmailVerificationPrompt;
use App\Filament\Admin\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset as AdminOtpRequestPasswordReset;
use App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword as AdminOtpResetPassword;
use App\Filament\Admin\Auth\VerifyOtp\VerifyOtp as AdminVerifyOtp;
use App\Filament\User\Auth\Auth\Auth as UserAuth;
use App\Filament\User\Auth\SignIn\SignIn as UserSignIn;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt as UserOtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset as UserOtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword as UserOtpResetPassword;
use App\Filament\User\Auth\SignUp\SignUp as UserSignUp;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp as UserVerifyOtp;
use App\Livewire\Admin\CatalogTables\CategoryTable as AdminCatalogCategoryTable;
use App\Livewire\Admin\CatalogTables\PackageTable as AdminCatalogPackageTable;
use App\Livewire\Admin\Messages\Inbox\Inbox as AdminMessagesInbox;
use App\Livewire\Admin\Messages\Messages\Messages as AdminMessagesContent;
use App\Livewire\Admin\Messages\Search\Search as AdminMessagesSearch;
use App\Livewire\Admin\UsernameComponent\UsernameComponent;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\BrowserSessionsComponent\BrowserSessionsComponent;
use App\Livewire\User\DeleteAccountComponent\DeleteAccountComponent;
use App\Livewire\User\EditPasswordComponent\EditPasswordComponent;
use App\Livewire\User\MobileSettingsComponent\MobileSettingsComponent;
use App\Livewire\User\AppLockComponent\AppLockComponent;
use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Livewire\User\Messages\Inbox\Inbox as UserMessagesInbox;
use App\Livewire\User\Messages\Messages\Messages as UserMessagesContent;
use App\Livewire\User\Messages\Search\Search as UserMessagesSearch;
use App\Livewire\Welcome\Messages\Inbox\Inbox as WelcomeMessagesInbox;
use App\Livewire\Welcome\Messages\Messages\Messages as WelcomeMessagesContent;
use App\Livewire\Welcome\Messages\Search\Search as WelcomeMessagesSearch;
use App\Livewire\User\NotificationSettingsComponent\NotificationSettingsComponent;
use App\Livewire\User\SecuritySettingsComponent\SecuritySettingsComponent;
use App\Models\BackupCode\BackupCode;
use App\Models\Bank\Bank;
use App\Models\Cart\Cart;
use App\Models\Category\Category;
use App\Models\Discount\Discount;
use App\Models\Help\Help;
use App\Models\History\History;
use App\Models\Inbox\Inbox;
use App\Models\LegalPage\LegalPage;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\PaymentGateway\PaymentGateway;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\Catalog\Catalog;
use App\Models\ReferenceOption\ReferenceOption;
use App\Services\GuestIdentity\GuestIdentity;
use App\Models\Report\Report;
use App\Models\Review\Review;
use App\Models\ReviewReply\ReviewReply;
use App\Models\ReviewVote\ReviewVote;
use App\Models\SecurityEmail\SecurityEmail;
use App\Models\TermsOfService\TermsOfService;
use App\Models\Transaction\Transaction;
use App\Models\Translation\Translation;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use App\Models\UserFcmDevice\UserFcmDevice;
use App\Models\UserLanguage\UserLanguage;
use App\Models\UserSession\UserSession;
use App\Models\Voucher\Voucher;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use App\Models\WhatsappOtp\WhatsappOtp;
use App\Models\Wishlist\Wishlist;
use App\Observers\MediaObserver\MediaObserver;
use App\Observers\MessageObserver\MessageObserver;
use App\Observers\OrderObserver\OrderObserver;
use App\Observers\TransactionObserver\TransactionObserver;
use App\Providers\Filament\UserPanelProvider\UserPanelProvider;
use App\Providers\FirebaseServiceProvider\FirebaseServiceProvider;
use App\Services\GeoLocationService\GeoLocationService;
use App\Support\AppPlatform\AppPlatform;
use App\Support\DeviceTrust\DeviceTrustService;
use App\Support\PlatformContext\PlatformContext;
use App\Support\PasswordPolicy\PasswordPolicy;
use Filament\Actions\Exports\ExportColumn;
use Filament\Forms\Components\Field;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Infolists\Components\Entry;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Filament\Events\ServingFilament;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Backup\BackupServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The resolved RuntimePlatform is memoised for the lifetime of one
        // request/worker cycle so a single page render can never see two
        // different answers. AppPlatform::current() reads it from here.
        $this->app->scoped(RuntimePlatform::class, fn () => AppPlatform::detect());

        // One guest identity per request, shared by every component that needs it.
        // The welcome Messages pages serve guests alongside members and must not
        // spin up a second cookie write or a second DB read for the same visit.
        $this->app->singleton(GuestIdentity::class);

        // FIX: model autentikasi aplikasi (App\Models\User\User), bukan
        // bawaan Filament (App\Models\User).
        //
        // Dipakai `Import::user()` (tabel `imports`, FK ke `users.id`) --
        // tombol "Ekspor Data" native sudah ditangani lewat relasi
        // polimorfik di bawah.
        $this->app->bind(
            \Illuminate\Contracts\Auth\Authenticatable::class,
            \App\Models\User\User::class
        );

        // ═══════════════════════════════════════════════════════════
        // FIX: Filament LoginResponse / RegisterResponse / LogoutResponse
        // Returning Livewire\Redirector instead of a RedirectResponse breaks
        // the HTTP response contract ("setContent(): Argument must be of type
        // ?string"). Bind implementations that always return RedirectResponse.
        // ═══════════════════════════════════════════════════════════
        $this->app->bind(LoginResponseContract::class, function () {
            return new class implements LoginResponseContract
            {
                public function toResponse($request)
                {
                    $panel = filament()->getCurrentPanel();
                    $url = $panel
                        ? $panel->getUrl()
                        : (session()->pull('url.intended') ?? '/');

                    return redirect()->to($url);
                }
            };
        });

        $this->app->bind(RegistrationResponseContract::class, function () {
            return new class implements RegistrationResponseContract
            {
                public function toResponse($request)
                {
                    $panel = filament()->getCurrentPanel();
                    $url = $panel
                        ? $panel->getUrl()
                        : (session()->pull('url.intended') ?? '/');

                    return redirect()->to($url);
                }
            };
        });

        // Catatan: redirect logout TIDAK diatur di sini.
        //
        // Filament\Http\Controllers\Auth\LogoutController mengembalikan
        // `app(Filament\Http\Responses\Auth\LogoutResponse::class)` -- kelas
        // NYATA, dan kelas itu sendiri tidak pernah me-resolve contract-nya. Jadi
        // binding ke interface LogoutResponse ( seperti dua binding di atas)
        // tidak akan pernah terpakai.
        //
        // Override yang benar ada di panel provider, lewat trait
        // RedirectsLogoutToWelcomeHome -> WelcomeLogoutResponse:
        // UserPanelProvider dan AdminPanelProvider (override `register()`),
        // supaya SignOut dari kedua panel mendarat di Welcome Home.

        if (class_exists('ZipArchive')) {
            if (class_exists(BackupServiceProvider::class)) {
                $this->app->register(BackupServiceProvider::class);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // FIX: filament-mobile-table compatibility with Filament v3/v4
        // Must be registered BEFORE other service providers boot so the
        // macros exist when FilamentMobileTableServiceProvider tries to use them.
        // ═══════════════════════════════════════════════════════════
        $this->app->booting(function (): void {
            // Use object property via array storage per-instance (PHP macros run bound to $this = Table instance)
            Table::macro('extraTableAttributes', function (array $attributes) {
                /** @var mixed $this */
                $key = '__mobileExtraAttrs';
                $existing = data_get((array) $this, $key, []);
                $merged = array_merge($existing, $attributes);
                // Store on the object via the Macroable mechanism
                $this->$key = $merged; // @phpstan-ignore property.notFound

                return $this;
            });
            Table::macro('getExtraTableAttributes', function () {
                /** @var mixed $this */
                $key = '__mobileExtraAttrs';

                return property_exists($this, $key) ? $this->$key : [];
            });
            Table::macro('extraAttributes', function (array $attributes) {
                /** @var mixed $this */
                $key = '__mobileExtraAttrs';
                $existing = data_get((array) $this, $key, []);
                $merged = array_merge($existing, $attributes);
                $this->$key = $merged; // @phpstan-ignore property.notFound

                return $this;
            });
            Table::macro('getExtraAttributes', function () {
                /** @var mixed $this */
                $key = '__mobileExtraAttrs';

                return property_exists($this, $key) ? $this->$key : [];
            });

            Table::macro('mobileCards', function (bool $condition = true) {
                /** @var mixed $this */
                return $this->extraTableAttributes(['mobile-cards' => $condition]);
            });

            Table::macro('mobileCardFeatured', function (string $column, string $color = 'primary') {
                /** @var mixed $this */
                return $this->extraTableAttributes([
                    'mobile-card-featured-column' => $column,
                    'mobile-card-featured-color' => $color,
                ]);
            });
        });
    }

    /**
     * Terjemahkan nilai kolom, TETAPI aman kalau kuncinya mengembalikan array.
     *
     * `__($key)` tidak selalu mengembalikan string. Sebuah kunci terjemahan
     * bisa bernilai ARRAY -- misalnya `lang`, `attributes`, `max`, `min`,
     * `size`, `password`, `between`, `named`, `target_link` (semua ada di
     * `lang/*.php` bawaan Laravel).
     *
     * Nilai kolom bisa saja persis salah satu kunci itu. Contoh nyata:
     * tabel `fm_app_settings` punya baris dengan `key` = "lang". Waktu
     * diekspor, `__("lang")` mengembalikan ARRAY, dan `(string) $array`
     * meledak dengan "Array to string conversion" -- export gagal total.
     *
     * Jadi: kalau hasil terjemahan bukan string, nilai ASLINYA yang dipakai.
     */
    protected static function translateValue(string $value): string
    {
        $translated = __($value);

        return is_string($translated) ? $translated : $value;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(config('security.rate_limits.api', 120))
                ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('auth', function ($request) {
            return Limit::perMinute(config('security.rate_limits.auth', 10))
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('admin', function ($request) {
            return Limit::perMinute(config('security.rate_limits.admin', 60))
                ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('guest-chat', function ($request) {
            $guestId = (string) $request->input('guest_id', '');
            return Limit::perMinute(config('security.rate_limits.guest_chat', 20))
                ->by(hash('sha256', $guestId.'|'.$request->ip()));
        });

        // IkoN vendor Filament ikut BERWARNA: alias internal (tombol aksi
        // bawaan, editor file upload, boolean, notifikasi, filter tabel...)
        // dan SEMUA nama Heroicon yang di-hardcode vendor ditimpa dengan
        // path SVG berwarna. Chevron TIDAK ikut -- ia tetap Heroicon.
        VendorIconAliases::register();

        // ── Relasi `user` pada Filament Export/Import jadi POLIMORFIK ────
        //
        // Aplikasi ini punya DUA model autentikasi: `users` (shopper + tamu,
        // guard `web`) dan `admins` (tim internal, guard `admin`). Tabel
        // `exports` asalnya hanya punya `user_id` dengan FK ke `users.id`,
        // jadi begitu admin dipisah ke tabelnya sendiri, asumsi Filament
        // ("semua yang export adalah baris `users`") sudah tidak benar.
        //
        // Tanpa baris ini gejalanya HALUS dan justru itu yang berbahaya:
        //   - `Export::user()` tetap `belongsTo(User::class)`, jadi
        //     `associate($admin)` menulis `admins.id` ke kolom yang maknanya
        //     `users.id`. Tidak error (FK tetap valid selama id-nya kebetulan
        //     ada), tapi export tercatat atas nama shopper yang tidak ada
        //     hubungannya dengan export tersebut.
        //   - Notifikasi "ekspor selesai" dikirim ke shopper itu, bukan ke
        //     admin yang menjemduh.
        //
        // Dengan relasi polimorfik, `user_type` menyimpan model aktualnya
        // (`App\Models\User\User` atau `App\Models\Admin\Admin`), jadi audit
        // trail-nya jujur dan `Export::user()` mengembalikan model yang benar.
        //
        // `Import` ikut, walau `ImportAction` belum dipakai di aplikasi ini --
        // tabel `imports` sudah ada dengan bentuk yang sama.
        //
        // @see \Database\Migrations\2026_10_21_120000_make_export_import_user_polymorphic
        \Filament\Actions\Exports\Models\Export::polymorphicUserRelationship();
        \Filament\Actions\Imports\Models\Import::polymorphicUserRelationship();

        // Provider khusus untuk guard `admin`.
        //
        // Driver `active-admin` = EloquentUserProvider biasa + penolakan akun
        // yang `active_status`-nya false. Daftarkan di sini (bukan memakai
        // `driver => 'eloquent'`) supaya aturan "admin nonaktif tidak bisa
        // login" ditegakkan di lapisan yang membaca database, bukan di tiap
        // halaman login yang mungkin lupa memeriksanya.
        //
        // Hanya berlaku untuk guard `admin`. Provider `users` tetap driver
        // 'eloquent' bawaan, jadi shopper tidak ikut terpengaruh.
        //
        // @see \App\Auth\ActiveAdminUserProvider
        Auth::provider('active-admin', function ($app, array $config) {
            return new \App\Auth\ActiveAdminUserProvider(
                $app['hash'],
                $config['model'],
            );
        });

        // Default aturan kata sandi untuk SELURUH aplikasi.
        //
        // Tanpa baris ini, `Password::default()` -- yang dipakai tiga
        // EditPasswordComponent, halaman reset kata sandi Filament, dan
        // beberapa form internal lain -- jatuh ke default Laravel yaitu
        // Password::min(8). Itu sebabnya kebijakan "minimal 12 karakter +
        // huruf besar/kecil/angka/simbol" hanya berlaku di sebagian form,
        // sementara halaman "Ubah Kata Sandi" menerima "password".
        //
        // Satu baris ini menutup semua celah itu tanpa harus menyentuh
        // tiap komponen satu per satu. Sumber kebenaran tetap
        // App\Support\PasswordPolicy\PasswordPolicy.
        \Illuminate\Validation\Rules\Password::defaults(
            fn (): \Illuminate\Validation\Rules\Password => PasswordPolicy::filamentRules()
        );

        // Models use a one-folder-per-class structure (App\Models\X\X), so the
        // framework's default factory<->model guessing must be re-mapped.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $base = preg_replace('/Factory$/', '', class_basename($factory));

            return 'App\\Models\\'.$base.'\\'.$base;
        });

        // Models were moved from App\Models\X to App\Models\X\X, but every
        // polymorphic column written before the move (model_has_roles,
        // personal_access_tokens, media.model_type, etc.) still stores the
        // legacy flat class name. Map those legacy names to the nested classes
        // so morph relationships resolve without a data migration.
        Relation::morphMap([
            'App\Models\BackupCode' => BackupCode::class,
            'App\Models\Bank' => Bank::class,
            'App\Models\Cart' => Cart::class,
            'App\Models\Category' => Category::class,
            'App\Models\Discount' => Discount::class,
            'App\Models\Help' => Help::class,
            'App\Models\History' => History::class,
            'App\Models\Inbox' => Inbox::class,
            'App\Models\LegalPage' => LegalPage::class,
            'App\Models\Message' => Message::class,
            'App\Models\Order' => Order::class,
            'App\Models\Package' => Package::class,
            'App\Models\PaymentGateway' => PaymentGateway::class,
            'App\Models\PaymentMethod' => PaymentMethod::class,
            'App\Models\PrivacyPolicy' => PrivacyPolicy::class,
            'App\Models\Catalog' => Catalog::class,
            'App\Models\ReferenceOption' => ReferenceOption::class,
            'App\Models\Report' => Report::class,
            'App\Models\Review' => Review::class,
            'App\Models\ReviewReply' => ReviewReply::class,
            'App\Models\ReviewVote' => ReviewVote::class,
            'App\Models\SecurityEmail' => SecurityEmail::class,
            'App\Models\TermsOfService' => TermsOfService::class,
            'App\Models\Transaction' => Transaction::class,
            'App\Models\Translation' => Translation::class,
            'App\Models\TrustedDevice' => TrustedDevice::class,
            'App\Models\User' => User::class,
            'App\Models\UserFcmDevice' => UserFcmDevice::class,
            'App\Models\UserLanguage' => UserLanguage::class,
            'App\Models\UserSession' => UserSession::class,
            
            'App\Models\Voucher' => Voucher::class,
            'App\Models\WeddingDecorationPolicy' => WeddingDecorationPolicy::class,
            'App\Models\WhatsappOtp' => WhatsappOtp::class,
            'App\Models\Wishlist' => Wishlist::class,
        ]);

        // ═══════════════════════════════════════════════════════════
        // VENDOR VIEW SEPARATION (Admin/vendor vs User/vendor)
        // Dynamically register namespace paths so Admin panel loads
        // resources/views/Admin/vendor and User panel loads resources/views/User/vendor
        // ═══════════════════════════════════════════════════════════
        $registerVendorViews = function (string $panelType): void {
            $vendorPath = resource_path("views/{$panelType}/vendor");
            if (! is_dir($vendorPath)) {
                return;
            }

            foreach (scandir($vendorPath) as $folder) {
                if ($folder !== '.' && $folder !== '..' && is_dir($vendorPath . '/' . $folder)) {
                    View::prependNamespace($folder, $vendorPath . '/' . $folder);
                }
            }
        };

        if (request()->is('admin*')) {
            $registerVendorViews('Admin');
        } else {
            $registerVendorViews('User');
        }

        // ═══════════════════════════════════════════════════════════
        // PANEL VIEW OVERRIDES (resources/views/{Panel}/panel-overrides)
        // A panel can drop or replace a single Filament component view without
        // forking the whole package: each sub-folder of panel-overrides is
        // prepended to that view namespace for the current panel only, so the
        // override file is found first and every other Filament view still
        // comes from the package.
        //
        // Currently only the 'welcome' panel uses this, for
        // filament-panels::components.user-menu -- see the file's own comment.
        // ═══════════════════════════════════════════════════════════
        $registerPanelOverrides = function (string $panelType): void {
            $overridesPath = resource_path("views/{$panelType}/panel-overrides");
            if (! is_dir($overridesPath)) {
                return;
            }

            foreach (scandir($overridesPath) as $folder) {
                if ($folder === '.' || $folder === '..' || ! is_dir($overridesPath . '/' . $folder)) {
                    continue;
                }

                // prependNamespace() unshifts onto the hint list, so a second
                // call in the same request would register the same path twice.
                if (in_array($overridesPath . '/' . $folder, View::getFinder()->getHints()[$folder] ?? [], true)) {
                    continue;
                }

                View::prependNamespace($folder, $overridesPath . '/' . $folder);
            }
        };

        Event::listen(ServingFilament::class, function () use ($registerPanelOverrides, $registerVendorViews): void {
            $panelId = filament()->getCurrentPanel()?->getId();
            if ($panelId === 'admin') {
                $registerVendorViews('Admin');
            } elseif ($panelId === 'user') {
                $registerVendorViews('User');
            } elseif ($panelId === 'welcome') {
                $registerVendorViews('User');
                $registerPanelOverrides('Welcome');
            }
        });

        // Register Firebase Service Provider
        $this->app->register(FirebaseServiceProvider::class);

        $isMobile = PlatformContext::isAnyMobile();

        // Force HTTPS hanya kalau diminta eksplisit lewat APP_FORCE_HTTPS_SCHEME.
        //
        // DULU: `if (str_contains(config('app.url'), 'https://')) URL::forceScheme('https');`
        // Itu bug. forceScheme() hanya menimpa skema, host tetap dari request.
        // Lokal: buka http://127.0.0.1:8000 -> asset jadi https://127.0.0.1:8000/...
        // -> browser gagal TLS -> halaman tanpa CSS (Laravel, Filament, Livewire).
        // Tunnel: skema https sudah benar dari X-Forwarded-Proto + trustProxies('*').

        // 🚀 Vercel Read-Only Filesystem Fix
        if (env('VERCEL')) {
            $storagePath = '/tmp/storage';
            if (! is_dir($storagePath)) {
                @mkdir($storagePath, 0777, true);
                @mkdir($storagePath.'/framework/views', 0777, true);
                @mkdir($storagePath.'/framework/cache/data', 0777, true);
                @mkdir($storagePath.'/framework/cache/filament', 0777, true);
                @mkdir($storagePath.'/framework/sessions', 0777, true);
                @mkdir($storagePath.'/logs', 0777, true);
                @mkdir($storagePath.'/livewire-tmp', 0777, true);
            }
            config([
                'view.compiled' => $storagePath.'/framework/views',
                'cache.stores.file.path' => $storagePath.'/framework/cache/data',
                'session.files' => $storagePath.'/framework/sessions',
                'filament.cache_path' => $storagePath.'/framework/cache/filament',
                'livewire.temporary_file_upload.directory' => $storagePath.'/livewire-tmp',
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // PERSISTENT SESSION CONFIGURATION (WEB & MOBILE)
        // ═══════════════════════════════════════════════════════════
        config([
            'session.expire_on_close' => false,
            'session.lottery' => [0, 100],
            'session.lifetime' => 525600,
        ]);

        // Grant all permissions to super_admin role
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
        Event::listen(
            LoginEvent::class,
            function ($event): void {
                $user = $event->user;
                if ($user instanceof User) {
                    if (! $user->active_status) {
                        $user->update(['active_status' => true]);
                    }

                    $ip = request()->ip();
                    $location = app(GeoLocationService::class)->lookup($ip);

                    $user->update(array_filter([
                        'ip_address' => $ip,
                        'login_city' => $location['city'] ?? null,
                        'login_region' => $location['region'] ?? null,
                        'login_country' => $location['country'] ?? null,
                    ]));

                    /*
                     * Notifikasi perangkat ala Google/Instagram:
                     *
                     * - perangkat BARU (fingerprint User-Agent belum pernah
                     *   dipakai akun ini) -> "Perangkat Baru -- Apakah Ini
                     *   Anda?" dengan tombol Tinjau Perangkat;
                     * - perangkat dikenal + sudah dinyatakan terpercaya ->
                     *   diam;
                     * - perangkat dikenal + belum dikonfirmasi -> perilaku
                     *   lama "Login Terdeteksi" tetap berjalan.
                     *
                     * Jalur API mobile (AuthController::login) tidak memicu
                     * event ini, jadi DeviceTrustService::recognize() juga
                     * dipanggil di sana.
                     */
                    DeviceTrustService::recognize($user);
                }
            }
        );
        Media::observe(MediaObserver::class);
        Message::observe(MessageObserver::class);
        Order::observe(OrderObserver::class);
        Transaction::observe(TransactionObserver::class);
        Livewire::component('edit-password-component', EditPasswordComponent::class);
        Livewire::component('mobile-settings-component', MobileSettingsComponent::class);
        Livewire::component('notification-settings-component', NotificationSettingsComponent::class);
        Livewire::component('security-settings-component', SecuritySettingsComponent::class);
        Livewire::component('app-lock-component', AppLockComponent::class);
        Livewire::component('complete-profile-component', CompleteProfileComponent::class);
        Livewire::component('username-component', UsernameComponent::class);
        Livewire::component('edit_password_form', EditPasswordComponent::class);
        Livewire::component('delete_account_form', DeleteAccountComponent::class);
        Livewire::component('browser_sessions_form', BrowserSessionsComponent::class);
        // Tabel kategori + tabel paket, masing-masing di halamannya sendiri
        // (/admin/categories dan /admin/packages). Didaftarkan sebagai
        // komponen Livewire supaya halaman itu bisa merender tabelnya --
        // lihat App\Filament\Admin\Resources\CategoryResource dan
        // App\Filament\Admin\Resources\PackageResource.
        Livewire::component('fm-admin-catalog-categories', AdminCatalogCategoryTable::class);
        Livewire::component('fm-admin-catalog-packages', AdminCatalogPackageTable::class);

        // Kustomizer gaya WordPress: panel "Tampilan Halaman Publik"
        // (tipografi per-peran + tombol + border) yang bisa dibuka dari
        // bawah konten SETIAP halaman admin (hook panels::page.end di
        // AdminPanelProvider) dan dari halaman terpusat Kustomisasi.
        Livewire::component('fm-admin-page-customizer', \App\Livewire\Admin\PageCustomizer\PageCustomizer::class);

        Livewire::component('fm-admin-inbox', AdminMessagesInbox::class);
        Livewire::component('fm-admin-messages', AdminMessagesContent::class);
        Livewire::component('fm-admin-search', AdminMessagesSearch::class);
        Livewire::component('fm-user-inbox', UserMessagesInbox::class);
        Livewire::component('fm-user-messages', UserMessagesContent::class);
        Livewire::component('fm-user-search', UserMessagesSearch::class);
        Livewire::component('fm-welcome-inbox', WelcomeMessagesInbox::class);
        Livewire::component('fm-welcome-messages', WelcomeMessagesContent::class);
        Livewire::component('fm-welcome-search', WelcomeMessagesSearch::class);
        Livewire::component('app.filament.admin.auth.sign-in', AdminSignIn::class);
        Livewire::component('app.filament.admin.auth.otp-request-password-reset', AdminOtpRequestPasswordReset::class);
        Livewire::component('app.filament.admin.auth.otp-reset-password', AdminOtpResetPassword::class);
        Livewire::component('app.filament.admin.auth.verify-otp', AdminVerifyOtp::class);
        Livewire::component('app.filament.admin.auth.otp-email-verification-prompt', AdminOtpEmailVerificationPrompt::class);
        Livewire::component('app.filament.user.auth.auth', UserAuth::class);
        Livewire::component('app.filament.user.auth.sign-in', UserSignIn::class);
        // Alias Sign Up aktif lagi, menyambung ke `->registration(SignUp::class)`
        // di UserPanelProvider. Tanpa alias ini halaman Sign Up me-render
        // component Livewire yang tidak dikenal dan gagal saat hydration.
        Livewire::component('app.filament.user.auth.sign-up', UserSignUp::class);
        Livewire::component('app.filament.user.auth.otp-request-password-reset', UserOtpRequestPasswordReset::class);
        Livewire::component('app.filament.user.auth.otp-reset-password', UserOtpResetPassword::class);
        Livewire::component('app.filament.user.auth.verify-otp', UserVerifyOtp::class);
        Livewire::component('app.filament.user.auth.otp-email-verification-prompt', UserOtpEmailVerificationPrompt::class);

        Table::configureUsing(function (Table $table): void {
            $table->searchable();
        });

        // Global column config
        Column::configureUsing(function (Column $column): void {
            $column->alignCenter()
                ->label(fn () => __($column->getName()));
        });

        // Auto-translate TextColumn — skip di mobile untuk performa
        if (! $isMobile) {
            TextColumn::configureUsing(function (TextColumn $column): void {
                $column->formatStateUsing(function ($state, $record, TextColumn $column) {
                    // JANGAN terjemahkan dua kali.
                    //
                    // Model yang field-nya ada di `$translatable`-nya sudah
                    // mengembalikan versi sesuai locale lewat accessor-nya.
                    // Terjemahkan lagi di kolom bikin yang tampil di layar
                    // BERBEDA dari yang tersimpan di database -- jadi admin
                    // mengedit teks yang tidak ada, dan cocokan nama paket
                    // dengan tier resmi ikut berubah.
                    if ($record instanceof Model && method_exists($record, 'hasTranslationFor') && $record->hasTranslationFor($column->getName())) {
                        return $state;
                    }

                    if (is_string($state) && ! filter_var($state, FILTER_VALIDATE_EMAIL) && ! str_contains($state, 'http')) {
                        if (! preg_match('/^[0-9.,\-+() ]+$/', $state)) {
                            return self::translateValue($state);
                        }
                    }

                    return $state;
                });
            });
        }

        // Auto-translate form fields
        Field::configureUsing(function (Field $field): void {
            $field->label(function () use ($field): string {
                $original = $field->getName();
                $translated = __($original);

                return is_string($translated) ? $translated : $original;
            });
        });

        BaseFilter::configureUsing(function (BaseFilter $filter): void {
            $filter->translateLabel();
        });

        Entry::configureUsing(function (Entry $entry): void {
            $entry->label(function () use ($entry): string {
                $original = $entry->getName();
                $translated = __($original);

                return is_string($translated) ? $translated : $original;
            });
        });

        // ExportColumn — skip di mobile (tidak ada fitur export di mobile)
        if (! $isMobile) {
            ExportColumn::configureUsing(function (ExportColumn $column): void {
                $column->formatStateUsing(function ($state) {
                    if (is_array($state)) {
                        return $state === [] ? null : json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    if (is_string($state) && ! filter_var($state, FILTER_VALIDATE_EMAIL) && ! str_contains($state, 'http')) {
                        if (! preg_match('/^[0-9.,\-+() ]+$/', $state)) {
                            $state = self::translateValue($state);
                        }
                    }
                    if ($state instanceof \UnitEnum) {
                        $state = $state instanceof \BackedEnum ? $state->value : $state->name;
                    }

                    return $state !== null ? (string) $state : null;
                });
            });
        }

        // 📱 MOBILE PAGINATION NAV
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function () use ($isMobile): string {
                $isMobileCheck = $isMobile || (bool) preg_match(
                    '/android|iphone|ipad|ipod|mobile|blackberry|windows phone|capacitor/i',
                    request()->userAgent() ?? ''
                );

                if ($isMobileCheck) {
                    return Blade::render('
                        <style>
                            .fi-ta-pagination { display: none !important; }
                            .fi-ta-filters-above-content-ctn { border-top: none !important; border-bottom: none !important; }
                            .fi-ta-filters-above-content-ctn > div { border-top: none !important; }
                        </style>
                        @include("User.components.mobile-pagination.mobile-pagination")
                    ');
                }

                return '';
            },
            scopes: UserPanelProvider::class,
        );
    }
}
