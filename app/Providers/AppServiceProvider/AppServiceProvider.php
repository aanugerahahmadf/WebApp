<?php

namespace App\Providers\AppServiceProvider;

use App\Database\MySqlProxyConnection\MySqlProxyConnection;
use App\Filament\Admin\Auth\Login\Login as AdminLogin;
use App\Filament\Admin\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt as AdminOtpEmailVerificationPrompt;
use App\Filament\Admin\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset as AdminOtpRequestPasswordReset;
use App\Filament\Admin\Auth\OtpResetPassword\OtpResetPassword as AdminOtpResetPassword;
use App\Filament\Admin\Auth\Register\Register as AdminRegister;
use App\Filament\Admin\Auth\VerifyOtp\VerifyOtp as AdminVerifyOtp;
use App\Filament\User\Auth\Login\Login as UserLogin;
use App\Filament\User\Auth\OtpEmailVerificationPrompt\OtpEmailVerificationPrompt as UserOtpEmailVerificationPrompt;
use App\Filament\User\Auth\OtpRequestPasswordReset\OtpRequestPasswordReset as UserOtpRequestPasswordReset;
use App\Filament\User\Auth\OtpResetPassword\OtpResetPassword as UserOtpResetPassword;
use App\Filament\User\Auth\Register\Register as UserRegister;
use App\Filament\User\Auth\VerifyOtp\VerifyOtp as UserVerifyOtp;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Livewire\Admin\Messages\Inbox\Inbox as AdminMessagesInbox;
use App\Livewire\Admin\Messages\Messages\Messages as AdminMessagesContent;
use App\Livewire\Admin\Messages\Search\Search as AdminMessagesSearch;
use App\Livewire\Admin\UsernameComponent\UsernameComponent;
use App\Livewire\Shared\BrowserSessionsComponent\BrowserSessionsComponent;
use App\Livewire\Shared\DeleteAccountComponent\DeleteAccountComponent;
use App\Livewire\Shared\EditPasswordComponent\EditPasswordComponent;
use App\Livewire\Shared\MobileSettingsComponent\MobileSettingsComponent;
use App\Livewire\User\AppLockComponent\AppLockComponent;
use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Livewire\User\Messages\Inbox\Inbox as UserMessagesInbox;
use App\Livewire\User\Messages\Messages\Messages as UserMessagesContent;
use App\Livewire\User\Messages\Search\Search as UserMessagesSearch;
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
use App\Models\Product\Product;
use App\Models\ReferenceOption\ReferenceOption;
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
use App\Models\Vendor\Vendor;
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
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Support\AndroidSdkEnvironment\AndroidSdkEnvironment;
use App\Support\PlatformContext\PlatformContext;
use Filament\Actions\Exports\ExportColumn;
use Filament\Forms\Components\Field;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Infolists\Components\Entry;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->runningInConsole()) {
            AndroidSdkEnvironment::apply();
        }

        // 🛠️ Development Shim for NativePHP Mobile
        // Prevents "Undefined function nativephp_call" when running on Windows/Desktop
        if (! function_exists('nativephp_call')) {
            require_once __DIR__.'/../../../bootstrap/nativephp_shim.php';
        }

        // ═══════════════════════════════════════════════════════════
        // FIX: Filament LoginResponse / RegisterResponse / LogoutResponse
        // returns Livewire\Redirector in NativePHP context which causes
        // "setContent(): Argument must be of type ?string" fatal error.
        // Override with implementations that always return RedirectResponse.
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

        $this->app->bind(LogoutResponseContract::class, function () {
            return new class implements LogoutResponseContract
            {
                public function toResponse($request)
                {
                    $panel = filament()->getCurrentPanel();
                    $url = $panel
                        ? $panel->getLoginUrl()
                        : route('filament.user.auth.login');

                    return redirect()->to($url);
                }
            };
        });

        // 🌉 Register MySQL Proxy Driver (For Mobile without pdo_mysql)
        $this->app->resolving('db', function ($db): void {
            $db->extend('mysql_proxy', function ($config, $name) {
                return new MySqlProxyConnection(
                    function () {
                        return new \stdClass;
                    }, // Fake PDO callback
                    $config['database'],
                    $config['prefix'],
                    $config
                );
            });
        });

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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
            'App\Models\Product' => Product::class,
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
            'App\Models\Vendor' => Vendor::class,
            'App\Models\Voucher' => Voucher::class,
            'App\Models\WeddingDecorationPolicy' => WeddingDecorationPolicy::class,
            'App\Models\WhatsappOtp' => WhatsappOtp::class,
            'App\Models\Wishlist' => Wishlist::class,
        ]);

        // Register Firebase Service Provider
        $this->app->register(FirebaseServiceProvider::class);

        $isMobile = PlatformContext::isAnyMobile();

        // 🚀 Force HTTPS for Ngrok/Production Assets
        if (str_contains(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

                    $locationParts = array_filter([
                        $location['city'] ?? null,
                        $location['region'] ?? null,
                        $location['country'] ?? null,
                    ]);

                    $locationText = $locationParts
                        ? implode(', ', $locationParts)
                        : __('Lokasi tidak diketahui');

                    PlatformNotificationService::send(
                        $user,
                        __('Login Terdeteksi'),
                        __('Akun Anda telah digunakan dari :ip (:location) pada :time.', [
                            'ip' => $ip,
                            'location' => $locationText,
                            'time' => now()->format('d M Y H:i:s'),
                        ]),
                        PasswordSecurityPage::getUrl(['section' => 'sign-in-activity'], panel: 'user'),
                        __('Lihat aktivitas Sign In'),
                    );
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
        Livewire::component('fm-admin-inbox', AdminMessagesInbox::class);
        Livewire::component('fm-admin-messages', AdminMessagesContent::class);
        Livewire::component('fm-admin-search', AdminMessagesSearch::class);
        Livewire::component('fm-user-inbox', UserMessagesInbox::class);
        Livewire::component('fm-user-messages', UserMessagesContent::class);
        Livewire::component('fm-user-search', UserMessagesSearch::class);
        Livewire::component('app.filament.admin.auth.login', AdminLogin::class);
        Livewire::component('app.filament.admin.auth.register', AdminRegister::class);
        Livewire::component('app.filament.admin.auth.otp-request-password-reset', AdminOtpRequestPasswordReset::class);
        Livewire::component('app.filament.admin.auth.otp-reset-password', AdminOtpResetPassword::class);
        Livewire::component('app.filament.admin.auth.verify-otp', AdminVerifyOtp::class);
        Livewire::component('app.filament.admin.auth.otp-email-verification-prompt', AdminOtpEmailVerificationPrompt::class);
        Livewire::component('app.filament.user.auth.login', UserLogin::class);
        Livewire::component('app.filament.user.auth.register', UserRegister::class);
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
                    if (is_string($state) && ! filter_var($state, FILTER_VALIDATE_EMAIL) && ! str_contains($state, 'http')) {
                        if (! preg_match('/^[0-9.,\-+() ]+$/', $state)) {
                            return __($state);
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
                    if (is_string($state) && ! filter_var($state, FILTER_VALIDATE_EMAIL) && ! str_contains($state, 'http')) {
                        if (! preg_match('/^[0-9.,\-+() ]+$/', $state)) {
                            $state = __($state);
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
                    '/android|iphone|ipad|ipod|mobile|blackberry|windows phone|nativephp/i',
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
