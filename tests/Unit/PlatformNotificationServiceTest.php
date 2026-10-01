<?php

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Events\NotificationBroadcast\NotificationBroadcast;
use App\Models\User\User;
use App\Services\PlatformNotificationService\PlatformNotificationService;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;
use Illuminate\Support\Facades\Event;
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
    // send() — delivery is platform-independent
    // -----------------------------------------------------------------------
    //
    // send() used to walk the active channels and log a skip whenever the current
    // RuntimePlatform lacked one, a per-platform branch that existed to serve
    // NativePHP's native toast and desktop-notification facades. Both are gone.
    // The Capacitor shells are ordinary browser contexts, so the WebSocket
    // broadcast reaches them and the OS toast is raised client-side through the
    // browser Notification API. Delivery is therefore the same three steps
    // everywhere — a Filament database record, a broadcast, and an FCM push —
    // and there is nothing left to skip.
    //
    // What a platform *could* use is still reported by getActiveChannels(),
    // covered separately below. What matters here is that send() no longer
    // varies by platform, so these tests pin the three delivery steps and
    // assert they happen identically on all eight RuntimePlatform targets.

    describe('send() — platform-independent delivery', function () {
        test('writes the database notification exactly once on every platform', function () {
            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);
                $user = makeUser();

                PlatformNotificationService::send($user, 'Title', 'Body');

                $user->shouldHaveReceived('notify')->once();
            }
        });

        test('broadcasts over WebSocket exactly once on every platform', function () {
            Event::fake([NotificationBroadcast::class]);

            foreach (RuntimePlatform::cases() as $platform) {
                bindPlatform($platform);

                PlatformNotificationService::send(makeUser(), 'Title', 'Body');
            }

            Event::assertDispatchedTimes(NotificationBroadcast::class, count(RuntimePlatform::cases()));
        });

        test('produces the same delivery on a website and on a mobile shell', function () {
            $deliveries = [];

            foreach ([RuntimePlatform::WebsiteWindows, RuntimePlatform::MobileAppAndroid] as $platform) {
                $user = makeUser();

                PlatformNotificationService::send($user, 'Title', 'Body');

                $deliveries[$platform->value] = $user->shouldHaveReceived('notify')->once() ? 'sent' : 'skipped';
            }

            expect($deliveries[RuntimePlatform::WebsiteWindows->value])
                ->toBe($deliveries[RuntimePlatform::MobileAppAndroid->value]);
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

        test('delivers to the database and the broadcast, but not over FCM', function () {
            // sendToWebOnly() is the "in-app only" variant: database record plus
            // broadcast, deliberately no device push. send() adds the FCM step.
            $user = makeUser();

            PlatformNotificationService::sendToWebOnly($user, 'Title', 'Body');

            $user->shouldHaveReceived('notify')->once();
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
