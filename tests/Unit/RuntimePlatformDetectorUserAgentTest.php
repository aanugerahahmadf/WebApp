<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Property Test: User Agent Detection Completeness
 *
 * Property 2: For ANY valid user agent string provided to the Platform Detector
 * in Web Server Mode, the detector SHALL return one of the four website
 * RuntimePlatform enum cases.
 *
 * Validates: Requirements 2.1, 10.5
 */
class RuntimePlatformDetectorUserAgentTest extends TestCase
{
    private RuntimePlatformDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RuntimePlatformDetector;
    }

    /**
     * Test that varied user agent strings all return valid website platforms
     */
    public function test_varied_user_agents_return_valid_website_platforms(): void
    {
        $userAgents = $this->generateVariedUserAgents();

        foreach ($userAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            // Assert that result is one of the four website RuntimePlatform cases
            $this->assertTrue(
                in_array($result, [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                ], true),
                "User agent '{$userAgent}' returned {$result->value}, expected one of the website platform cases"
            );

            // Also verify using the enum's isWebsite() method
            $this->assertTrue(
                $result->isWebsite(),
                "User agent '{$userAgent}' returned {$result->value} which is not a website platform"
            );
        }
    }

    /**
     * Test edge cases: empty strings, malformed agents
     */
    public function test_edge_case_user_agents_return_valid_website_platforms(): void
    {
        $edgeCases = [
            '',  // Empty string
            '   ',  // Whitespace only
            'Unknown',  // Generic unknown
            'Mozilla',  // Incomplete
            'Bot/1.0',  // Bot user agent
            '12345',  // Numeric only
            'null',  // String "null"
            '(null)',  // Null in parentheses
            'test/test/test',  // Unusual format
            str_repeat('a', 1000),  // Very long string
        ];

        foreach ($edgeCases as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            // Must return a valid website platform
            $this->assertTrue(
                $result->isWebsite(),
                "Edge case user agent '{$userAgent}' did not return a website platform: {$result->value}"
            );
        }
    }

    /**
     * Test iOS device user agents
     */
    public function test_ios_user_agents_return_website_ios(): void
    {
        $iosUserAgents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPod touch; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15',
        ];

        foreach ($iosUserAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertSame(
                RuntimePlatform::WebsiteIos,
                $result,
                "iOS user agent should return WebsiteIos: {$userAgent}"
            );
        }
    }

    /**
     * Test Android device user agents
     */
    public function test_android_user_agents_return_website_android(): void
    {
        $androidUserAgents = [
            'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36',
            'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; SCH-I535 Build/KOT49H) AppleWebKit/534.30',
        ];

        foreach ($androidUserAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertSame(
                RuntimePlatform::WebsiteAndroid,
                $result,
                "Android user agent should return WebsiteAndroid: {$userAgent}"
            );
        }
    }

    /**
     * Test macOS user agents
     */
    public function test_macos_user_agents_return_website_macos(): void
    {
        $macUserAgents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        ];

        foreach ($macUserAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertSame(
                RuntimePlatform::WebsiteMacOS,
                $result,
                "macOS user agent should return WebsiteMacOS: {$userAgent}"
            );
        }
    }

    /**
     * Test Windows user agents (default case)
     */
    public function test_windows_user_agents_return_website_windows(): void
    {
        $windowsUserAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)',
        ];

        foreach ($windowsUserAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertSame(
                RuntimePlatform::WebsiteWindows,
                $result,
                "Windows user agent should return WebsiteWindows: {$userAgent}"
            );
        }
    }

    /**
     * Test unusual browsers return valid website platforms
     */
    public function test_unusual_browsers_return_valid_website_platforms(): void
    {
        $unusualUserAgents = [
            'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Brave/91.0.4472.124',
            'Mozilla/5.0 (compatible; Konqueror/4.5; Linux) KHTML/4.5.4 (like Gecko)',
            'Lynx/2.8.9rel.1 libwww-FM/2.14 SSL-MM/1.4.1 OpenSSL/1.1.1',
        ];

        foreach ($unusualUserAgents as $userAgent) {
            $request = $this->createRequestWithUserAgent($userAgent);
            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertTrue(
                $result->isWebsite(),
                "Unusual browser user agent should return a website platform: {$userAgent}"
            );
        }
    }

    /**
     * Generate a diverse set of user agent strings for property testing
     */
    private function generateVariedUserAgents(): array
    {
        return [
            // Modern Chrome on various platforms
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',

            // Firefox variations
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/120.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/120.0',

            // Safari on different devices
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',

            // Android Chrome
            'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 12; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',

            // Edge
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',

            // Opera
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/105.0.0.0',

            // Older browsers
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)',

            // Mobile browsers
            'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.6099.119 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36',
        ];
    }

    /**
     * Create a mock request with the given user agent
     */
    private function createRequestWithUserAgent(string $userAgent): Request
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', $userAgent);

        return $request;
    }
}
