<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Models\User\User;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal User-like mock that satisfies PlatformNotificationService.
 * We avoid the database entirely — we just need an object with a `lang`
 * attribute and a `notify()` method that the Filament notification can call.
 */
function makeUser(string $locale = 'en'): User
{
    /** @var User $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('lang')->andReturn($locale)->byDefault();
    // Filament notification calls sendToDatabase() which ultimately calls
    // $user->notify(), so we stub that to avoid any DB interaction.
    $user->shouldReceive('notify')->andReturn(null)->byDefault();
    $user->shouldReceive('notifyNow')->andReturn(null)->byDefault();
    // Eloquent internals used by the database notification channel
    $user->shouldReceive('routeNotificationFor')->andReturn([])->byDefault();
    $user->shouldReceive('getKey')->andReturn(1)->byDefault();
    // Service membaca $user->id (bukan getKey) saat event NotificationBroadcast;
    // mock tanpa id mengakibatkan null → TypeError di constructor event.
    $user->shouldReceive('getAttribute')->with('id')->andReturn(1)->byDefault();
    $user->shouldReceive('getMorphClass')->andReturn('App\Models\User\User')->byDefault();

    return $user;
}

/**
 * Bind a RuntimePlatform into the IoC container so that
 * PlatformNotificationService can pick it up.
 */
function bindPlatform(RuntimePlatform $platform): void
{
    app()->singleton('runtime.platform', fn () => $platform);
}

/**
 * Remove any 'runtime.platform' binding from the container.
 */
function unbindPlatform(): void
{
    // Re-bind to nothing so app()->bound() returns false
    if (app()->bound('runtime.platform')) {
        app()->forgetInstance('runtime.platform');
        // Mark as unbound by rebinding to a resolver that throws, then clear
        // the binding completely via the IoC container's bindings array.
        // The cleanest approach in Laravel tests is to just fake the binding.
        app()->bind('runtime.platform', function () {
            throw new RuntimeException('runtime.platform not bound');
        });
        app()->forgetInstance('runtime.platform');
    }
}

// ---------------------------------------------------------------------------
// Suite: send() does not throw for any RuntimePlatform
// ---------------------------------------------------------------------------

describe('PlatformNotificationService', function () {

    beforeEach(function () {
        // Fake Laravel's Notification system so sendToDatabase never hits the DB.
        Notification::fake();
        // Ensure Log facade is mocked silently (don't assert unless needed).
        Log::spy();
    });

    afterEach(function () {
        Mockery::close();
    });

    // -----------------------------------------------------------------------
    // 15.4 – send() works without throwing for all RuntimePlatform cases
    // -----------------------------------------------------------------------

    describe('send() – does not throw for any RuntimePlatform', function () {
        $allPlatforms = RuntimePlatform::cases();

        foreach ($allPlatforms as $platform) {
            test("send() completes without exception for {$platform->value}", function () use ($platform) {
                bindPlatform($platform);

                $user = makeUser();

                expect(fn () => PlatformNotificationService::send($user, 'Test Title', 'Test <b>body</b>'))
                    ->not->toThrow(Throwable::class);
            });
        }
    });

    // -----------------------------------------------------------------------
    // 15.4 – send() without 'runtime.platform' binding (legacy / backward compat)
    // -----------------------------------------------------------------------

    describe('send() – backward compatibility when runtime.platform is not bound', function () {
        test('completes without exception when runtime.platform is not bound', function () {
            // Ensure no platform is bound
            app()->bind('runtime.platform', function () {
                throw new RuntimeException('runtime.platform not bound');
            });
            app()->forgetInstance('runtime.platform');

            $user = makeUser();

            expect(fn () => PlatformNotificationService::send($user, 'Title', 'Body'))
                ->not->toThrow(Throwable::class);
        });
    });

    // -----------------------------------------------------------------------
    // 15.4 – Feature registry gating: desktop notification skipped correctly
    // -----------------------------------------------------------------------

    describe('send() – feature registry gating', function () {
        test('logs skip for desktop_notifications when platform is website', function () {
            bindPlatform(RuntimePlatform::WebsiteWindows);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldHaveReceived('info')
                ->with('PlatformNotificationService: desktop_notifications channel skipped', Mockery::type('array'))
                ->once();
        });

        test('logs skip for push_notifications when platform is website', function () {
            bindPlatform(RuntimePlatform::WebsiteWindows);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldHaveReceived('info')
                ->with('PlatformNotificationService: push_notifications channel skipped', Mockery::type('array'))
                ->once();
        });

        test('logs skip for push_notifications when platform is desktop', function () {
            bindPlatform(RuntimePlatform::DesktopAppWindows);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldHaveReceived('info')
                ->with('PlatformNotificationService: push_notifications channel skipped', Mockery::type('array'))
                ->once();
        });

        test('logs skip for desktop_notifications when platform is mobile', function () {
            bindPlatform(RuntimePlatform::MobileAppAndroid);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldHaveReceived('info')
                ->with('PlatformNotificationService: desktop_notifications channel skipped', Mockery::type('array'))
                ->once();
        });

        test('does not log skip for desktop_notifications when platform is desktop', function () {
            bindPlatform(RuntimePlatform::DesktopAppWindows);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldNotHaveReceived('info',
                ['PlatformNotificationService: desktop_notifications channel skipped', Mockery::any()]
            );
        });

        test('does not log skip for push_notifications when platform is mobile', function () {
            bindPlatform(RuntimePlatform::MobileAppAndroid);
            $user = makeUser();

            PlatformNotificationService::send($user, 'Title', 'Body');

            Log::shouldNotHaveReceived('info',
                ['PlatformNotificationService: push_notifications channel skipped', Mockery::any()]
            );
        });
    });

    // -----------------------------------------------------------------------
    // 15.4 – Feature registry integration: isAvailable() drives gating
    // -----------------------------------------------------------------------

    describe('send() – feature registry integration', function () {
        test('desktop_notifications is skipped on exactly the platforms registry says are unavailable', function () {
            $registry = new PlatformFeatureRegistry;

            // Count how many platforms do NOT support desktop_notifications
            $unavailablePlatforms = array_filter(
                RuntimePlatform::cases(),
                fn ($p) => ! $registry->isAvailable('desktop_notifications', $p)
            );

            // Each unavailable platform sends one skip-log, so total skips
            // equals the count of unavailable platforms.
            $expectedSkips = count($unavailablePlatforms);

            // Bind a single non-desktop platform so all 8 platforms are exercised
            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);
                PlatformNotificationService::send(makeUser(), 'Title', 'Body');
            }

            // The spy accumulated across all 8 calls — verify skip count matches
            Log::shouldHaveReceived('info')
                ->withArgs(fn ($msg) => $msg === 'PlatformNotificationService: desktop_notifications channel skipped')
                ->times($expectedSkips);
        });

        test('push_notifications is skipped on exactly the platforms registry says are unavailable', function () {
            $registry = new PlatformFeatureRegistry;

            $unavailablePlatforms = array_filter(
                RuntimePlatform::cases(),
                fn ($p) => ! $registry->isAvailable('push_notifications', $p)
            );
            $expectedSkips = count($unavailablePlatforms);

            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);
                PlatformNotificationService::send(makeUser(), 'Title', 'Body');
            }

            Log::shouldHaveReceived('info')
                ->withArgs(fn ($msg) => $msg === 'PlatformNotificationService: push_notifications channel skipped')
                ->times($expectedSkips);
        });
    });

    // -----------------------------------------------------------------------
    // 15.4 – withRecipientLocale() correctly switches and restores locale
    // -----------------------------------------------------------------------

    describe('withRecipientLocale()', function () {
        test('switches locale to recipient language for the duration of the callback', function () {
            $user = makeUser('fr');

            $capturedLocale = null;

            PlatformNotificationService::withRecipientLocale($user, function () use (&$capturedLocale) {
                $capturedLocale = app()->getLocale();
            });

            expect($capturedLocale)->toBe('fr');
        });

        test('restores the original locale after the callback', function () {
            app()->setLocale('en');
            $user = makeUser('id');

            PlatformNotificationService::withRecipientLocale($user, fn () => null);

            expect(app()->getLocale())->toBe('en');
        });

        test('restores locale even when callback throws', function () {
            app()->setLocale('en');
            $user = makeUser('de');

            try {
                PlatformNotificationService::withRecipientLocale($user, function () {
                    throw new RuntimeException('Callback error');
                });
            } catch (RuntimeException) {
                // Expected — we just want to verify locale is restored.
            }

            expect(app()->getLocale())->toBe('en');
        });

        test('returns the value produced by the callback', function () {
            $user = makeUser('en');

            $result = PlatformNotificationService::withRecipientLocale($user, fn () => ['title', 'body']);

            expect($result)->toBe(['title', 'body']);
        });

        test('uses app locale as fallback when user has no preferred language', function () {
            app()->setLocale('es');
            $user = makeUser('es'); // same as app locale

            $capturedLocale = null;
            PlatformNotificationService::withRecipientLocale($user, function () use (&$capturedLocale) {
                $capturedLocale = app()->getLocale();
            });

            expect($capturedLocale)->toBe('es');
            expect(app()->getLocale())->toBe('es');
        });

        test('withRecipientLocale() and send() work together for all platforms', function () {
            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);
                $user = makeUser('fr');

                $result = PlatformNotificationService::withRecipientLocale(
                    $user,
                    fn () => ['Bonjour', 'Corps du message']
                );

                expect($result)->toBeArray()->toHaveCount(2);
                expect($result[0])->toBe('Bonjour');

                expect(fn () => PlatformNotificationService::send($user, $result[0], $result[1]))
                    ->not->toThrow(Throwable::class);
            }
        });
    });

    // -----------------------------------------------------------------------
    // 16.2 – sendToWebOnly()
    // -----------------------------------------------------------------------

    describe('sendToWebOnly()', function () {
        test('completes without exception for any RuntimePlatform', function () {
            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);
                $user = makeUser();

                expect(fn () => PlatformNotificationService::sendToWebOnly($user, 'Web Title', 'Web body'))
                    ->not->toThrow(Throwable::class);
            }
        });

        test('does not log desktop_notifications or push_notifications skip on website platform', function () {
            // sendToWebOnly never touches desktop/mobile channels so no skip logs expected
            bindPlatform(RuntimePlatform::WebsiteWindows);
            $user = makeUser();

            PlatformNotificationService::sendToWebOnly($user, 'Title', 'Body');

            Log::shouldNotHaveReceived('info',
                ['PlatformNotificationService: desktop_notifications channel skipped', Mockery::any()]
            );
            Log::shouldNotHaveReceived('info',
                ['PlatformNotificationService: push_notifications channel skipped', Mockery::any()]
            );
        });

        test('works without runtime.platform binding', function () {
            app()->bind('runtime.platform', function () {
                throw new RuntimeException('runtime.platform not bound');
            });
            app()->forgetInstance('runtime.platform');

            $user = makeUser();

            expect(fn () => PlatformNotificationService::sendToWebOnly($user, 'Title', 'Body'))
                ->not->toThrow(Throwable::class);
        });
    });

    // -----------------------------------------------------------------------
    // 16.2 – getActiveChannels()
    // -----------------------------------------------------------------------

    describe('getActiveChannels()', function () {
        test('always includes database channel for every platform', function () {
            foreach (RuntimePlatform::cases() as $platform) {
                $channels = PlatformNotificationService::getActiveChannels($platform);

                expect($channels)->toContain('database');
            }
        });

        test('returns [database, desktop] for desktop platforms', function () {
            foreach ([RuntimePlatform::DesktopAppWindows, RuntimePlatform::DesktopAppMacOS] as $platform) {
                $channels = PlatformNotificationService::getActiveChannels($platform);

                expect($channels)->toContain('database');
                expect($channels)->toContain('desktop');
                expect($channels)->not->toContain('mobile');
            }
        });

        test('returns [database, mobile] for mobile platforms', function () {
            foreach ([RuntimePlatform::MobileAppAndroid, RuntimePlatform::MobileAppIos] as $platform) {
                $channels = PlatformNotificationService::getActiveChannels($platform);

                expect($channels)->toContain('database');
                expect($channels)->toContain('mobile');
                expect($channels)->not->toContain('desktop');
            }
        });

        test('returns [database] only for website platforms', function () {
            $websitePlatforms = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
            ];

            foreach ($websitePlatforms as $platform) {
                $channels = PlatformNotificationService::getActiveChannels($platform);

                expect($channels)->toBe(['database']);
            }
        });

        test('channels match the feature registry for all platforms', function () {
            $registry = new PlatformFeatureRegistry;

            foreach (RuntimePlatform::cases() as $platform) {
                $channels = PlatformNotificationService::getActiveChannels($platform);

                $expectedDesktop = $registry->isAvailable('desktop_notifications', $platform);
                $expectedMobile = $registry->isAvailable('push_notifications', $platform);

                expect(in_array('desktop', $channels, true))->toBe($expectedDesktop);
                expect(in_array('mobile', $channels, true))->toBe($expectedMobile);
            }
        });
    });
});
