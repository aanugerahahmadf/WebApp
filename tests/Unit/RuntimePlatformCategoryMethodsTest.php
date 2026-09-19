<?php

namespace Tests\Unit;

use App\Enums\RuntimePlatform\RuntimePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Property 12: RuntimePlatform Category Methods Are Mutually Exclusive
 *
 * For ALL RuntimePlatform enum cases, exactly ONE of isWebsite(), isDesktopApp(),
 * isMobileApp() SHALL return true, and the other two SHALL return false.
 *
 * Validates: Requirements 10.6
 */
class RuntimePlatformCategoryMethodsTest extends TestCase
{
    public static function allPlatformCases(): array
    {
        return array_map(fn ($case) => [$case], RuntimePlatform::cases());
    }

    public static function websiteCases(): array
    {
        return array_map(fn ($case) => [$case], [
            RuntimePlatform::WebsiteWindows,
            RuntimePlatform::WebsiteMacOS,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
        ]);
    }

    public static function desktopCases(): array
    {
        return array_map(fn ($case) => [$case], [
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ]);
    }

    public static function mobileCases(): array
    {
        return array_map(fn ($case) => [$case], [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ]);
    }

    #[DataProvider('allPlatformCases')]
    public function test_exactly_one_category_method_returns_true(RuntimePlatform $platform): void
    {
        $isWebsite = $platform->isWebsite();
        $isDesktopApp = $platform->isDesktopApp();
        $isMobileApp = $platform->isMobileApp();

        $trueCount = (int) $isWebsite + (int) $isDesktopApp + (int) $isMobileApp;

        $this->assertSame(
            1,
            $trueCount,
            "Platform [{$platform->value}]: expected exactly 1 of isWebsite/isDesktopApp/isMobileApp to return true, got {$trueCount}."
        );
    }

    #[DataProvider('websiteCases')]
    public function test_website_cases_have_is_website_true(RuntimePlatform $platform): void
    {
        $websiteCases = [
            RuntimePlatform::WebsiteWindows,
            RuntimePlatform::WebsiteMacOS,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
        ];

        if (in_array($platform, $websiteCases, true)) {
            $this->assertTrue($platform->isWebsite(), "isWebsite() should be true for {$platform->value}");
            $this->assertFalse($platform->isDesktopApp(), "isDesktopApp() should be false for {$platform->value}");
            $this->assertFalse($platform->isMobileApp(), "isMobileApp() should be false for {$platform->value}");
        }
    }

    #[DataProvider('desktopCases')]
    public function test_desktop_cases_have_is_desktop_app_true(RuntimePlatform $platform): void
    {
        $desktopCases = [
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
        ];

        if (in_array($platform, $desktopCases, true)) {
            $this->assertFalse($platform->isWebsite(), "isWebsite() should be false for {$platform->value}");
            $this->assertTrue($platform->isDesktopApp(), "isDesktopApp() should be true for {$platform->value}");
            $this->assertFalse($platform->isMobileApp(), "isMobileApp() should be false for {$platform->value}");
        }
    }

    #[DataProvider('mobileCases')]
    public function test_mobile_cases_have_is_mobile_app_true(RuntimePlatform $platform): void
    {
        $mobileCases = [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ];

        if (in_array($platform, $mobileCases, true)) {
            $this->assertFalse($platform->isWebsite(), "isWebsite() should be false for {$platform->value}");
            $this->assertFalse($platform->isDesktopApp(), "isDesktopApp() should be false for {$platform->value}");
            $this->assertTrue($platform->isMobileApp(), "isMobileApp() should be true for {$platform->value}");
        }
    }

    #[DataProvider('allPlatformCases')]
    public function test_is_mobile_shell_includes_mobile_apps_and_mobile_websites(RuntimePlatform $platform): void
    {
        $mobileShellCases = [
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
        ];

        $expected = in_array($platform, $mobileShellCases, true);
        $this->assertSame(
            $expected,
            $platform->isMobileShell(),
            "isMobileShell() for {$platform->value} should be ".($expected ? 'true' : 'false')
        );
    }

    public function test_all_eight_cases_are_covered(): void
    {
        $this->assertCount(8, RuntimePlatform::cases(), 'RuntimePlatform should have exactly 8 cases');
    }
}
