<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Property Test: Platform Detection Always Returns Valid Enum
 *
 * Property 3: For ANY platform detection inputs (including mode, request, device info),
 * the RuntimePlatformDetector SHALL return a value that is a valid case of the
 * RuntimePlatform enum.
 *
 * Validates: Requirements 2.4
 */
class RuntimePlatformDetectorEnumTest extends TestCase
{
    private RuntimePlatformDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RuntimePlatformDetector;
    }

    /**
     * Test that all platform mode combinations return valid RuntimePlatform enum cases
     */
    public function test_all_platform_modes_return_valid_enum_cases(): void
    {
        $allModes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        foreach ($allModes as $mode) {
            $result = $this->detector->detect($mode, null);

            // Assert result is an instance of RuntimePlatform enum
            $this->assertInstanceOf(
                RuntimePlatform::class,
                $result,
                "Detection for mode {$mode->value} did not return a RuntimePlatform enum"
            );

            // Assert result is one of the valid enum cases
            $this->assertTrue(
                $this->isValidRuntimePlatformCase($result),
                "Detection for mode {$mode->value} returned invalid enum case: {$result->value}"
            );
        }
    }

    /**
     * Test Web mode with various request configurations returns valid enums
     */
    public function test_web_mode_with_various_requests_returns_valid_enums(): void
    {
        $requests = [
            null,  // No request
            Request::create('/test', 'GET'),  // Request without user agent
            $this->createRequestWithUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
            $this->createRequestWithUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'),
            $this->createRequestWithUserAgent('Mozilla/5.0 (Linux; Android 11)'),
            $this->createRequestWithUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 14_6)'),
            $this->createRequestWithUserAgent(''),  // Empty user agent
            $this->createRequestWithUserAgent(str_repeat('x', 5000)),  // Very long user agent
        ];

        foreach ($requests as $request) {
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertInstanceOf(
                RuntimePlatform::class,
                $result,
                'Web mode detection did not return a RuntimePlatform enum'
            );

            $this->assertTrue(
                $this->isValidRuntimePlatformCase($result),
                "Web mode returned invalid enum case: {$result->value}"
            );
        }
    }

    /**
     * Test Mobile mode returns valid enum cases
     */
    public function test_mobile_mode_returns_valid_enum_cases(): void
    {
        $result = $this->detector->detect(PlatformMode::Mobile, null);

        $this->assertInstanceOf(RuntimePlatform::class, $result);
        $this->assertTrue($this->isValidRuntimePlatformCase($result));

        // Mobile mode should only return mobile app platforms
        $this->assertTrue(
            in_array($result, [
                RuntimePlatform::MobileAppAndroid,
                RuntimePlatform::MobileAppIos,
            ], true),
            "Mobile mode should return MobileAppAndroid or MobileAppIos, got {$result->value}"
        );
    }

    /**
     * Test Desktop mode returns valid enum cases
     */
    public function test_desktop_mode_returns_valid_enum_cases(): void
    {
        $result = $this->detector->detect(PlatformMode::Desktop, null);

        $this->assertInstanceOf(RuntimePlatform::class, $result);
        $this->assertTrue($this->isValidRuntimePlatformCase($result));

        // Desktop mode should only return desktop app platforms
        $this->assertTrue(
            in_array($result, [
                RuntimePlatform::DesktopAppWindows,
                RuntimePlatform::DesktopAppMacOS,
            ], true),
            "Desktop mode should return DesktopAppWindows or DesktopAppMacOS, got {$result->value}"
        );
    }

    /**
     * Test with multiple rapid sequential detections (stress test)
     */
    public function test_rapid_sequential_detections_return_valid_enums(): void
    {
        $iterations = 100;
        $modes = [PlatformMode::Web, PlatformMode::Mobile, PlatformMode::Desktop];

        for ($i = 0; $i < $iterations; $i++) {
            $mode = $modes[array_rand($modes)];
            $request = $mode === PlatformMode::Web
                ? $this->createRandomRequest()
                : null;

            $result = $this->detector->detect($mode, $request);

            $this->assertInstanceOf(
                RuntimePlatform::class,
                $result,
                "Iteration {$i}: Detection did not return a RuntimePlatform enum"
            );

            $this->assertTrue(
                $this->isValidRuntimePlatformCase($result),
                "Iteration {$i}: Returned invalid enum case: {$result->value}"
            );
        }
    }

    /**
     * Test that enum values are correctly typed strings
     */
    public function test_returned_enums_have_valid_string_values(): void
    {
        $allModes = [PlatformMode::Web, PlatformMode::Mobile, PlatformMode::Desktop];

        foreach ($allModes as $mode) {
            $result = $this->detector->detect($mode, null);

            // Verify the value property is a string
            $this->assertIsString($result->value);

            // Verify the value is not empty
            $this->assertNotEmpty($result->value);

            // Verify the value matches the expected pattern
            $this->assertMatchesRegularExpression(
                '/^(website|desktop_app|mobile_app)_(windows|macos|android|ios)$/',
                $result->value,
                "Enum value '{$result->value}' does not match expected pattern"
            );
        }
    }

    /**
     * Test that returned enums have working category methods
     */
    public function test_returned_enums_have_working_category_methods(): void
    {
        $testCases = [
            ['mode' => PlatformMode::Web, 'request' => $this->createRequestWithUserAgent('Mozilla/5.0 (Windows NT 10.0)')],
            ['mode' => PlatformMode::Mobile, 'request' => null],
            ['mode' => PlatformMode::Desktop, 'request' => null],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->detector->detect($testCase['mode'], $testCase['request']);

            // Verify category methods exist and return booleans
            $this->assertIsBool($result->isWebsite());
            $this->assertIsBool($result->isDesktopApp());
            $this->assertIsBool($result->isMobileApp());

            // Verify exactly one category method returns true (mutual exclusivity)
            $trueCount = 0;
            if ($result->isWebsite()) {
                $trueCount++;
            }
            if ($result->isDesktopApp()) {
                $trueCount++;
            }
            if ($result->isMobileApp()) {
                $trueCount++;
            }

            $this->assertSame(
                1,
                $trueCount,
                "Enum {$result->value} should have exactly one category method return true, got {$trueCount}"
            );
        }
    }

    /**
     * Check if a result is a valid RuntimePlatform enum case
     */
    private function isValidRuntimePlatformCase(RuntimePlatform $platform): bool
    {
        $validCases = [
            RuntimePlatform::WebsiteWindows,
            RuntimePlatform::WebsiteMacOS,
            RuntimePlatform::WebsiteAndroid,
            RuntimePlatform::WebsiteIos,
            RuntimePlatform::DesktopAppWindows,
            RuntimePlatform::DesktopAppMacOS,
            RuntimePlatform::MobileAppAndroid,
            RuntimePlatform::MobileAppIos,
        ];

        return in_array($platform, $validCases, true);
    }

    /**
     * Create a request with a specific user agent
     */
    private function createRequestWithUserAgent(string $userAgent): Request
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', $userAgent);

        return $request;
    }

    /**
     * Create a request with a random user agent
     */
    private function createRandomRequest(): Request
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (Linux; Android 11)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6)',
            '',
        ];

        return $this->createRequestWithUserAgent($userAgents[array_rand($userAgents)]);
    }
}
