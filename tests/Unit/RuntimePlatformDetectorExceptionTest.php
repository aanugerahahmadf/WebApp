<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Property Test: Platform Detection Failure Defaults to WebsiteWindows
 *
 * Property 4: For ANY exception or error that occurs during platform detection,
 * the Platform Detector SHALL catch the exception, log a warning, and return
 * RuntimePlatform::WebsiteWindows as the default value.
 *
 * Validates: Requirements 2.6
 */
class RuntimePlatformDetectorExceptionTest extends TestCase
{
    private RuntimePlatformDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new RuntimePlatformDetector;
    }

    /**
     * Test that detection with malformed request returns WebsiteWindows
     */
    public function test_malformed_request_defaults_to_website_windows(): void
    {
        // Create a request that might cause issues
        $request = new class extends Request
        {
            public function userAgent()
            {
                throw new \RuntimeException('User agent retrieval failed');
            }
        };

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Platform detection failed'
                    && isset($context['mode'])
                    && isset($context['error']);
            });

        $result = $this->detector->detect(PlatformMode::Web, $request);

        $this->assertSame(
            RuntimePlatform::WebsiteWindows,
            $result,
            'Detection with exception should default to WebsiteWindows'
        );
    }

    /**
     * Test recovery behavior across all platform modes with Web mode
     *
     * Note: Only Web mode uses the request object that can throw exceptions.
     * Mobile and Desktop modes use different detection methods that don't
     * involve the request object, so they won't trigger the same exception path.
     */
    public function test_exception_recovery_in_web_mode(): void
    {
        // Create a problematic request
        $badRequest = new class extends Request
        {
            public function userAgent()
            {
                throw new \Exception('Simulated error');
            }

            public function header($key = null, $default = null)
            {
                throw new \Exception('Simulated header error');
            }
        };

        Log::shouldReceive('warning')->atLeast()->once();

        // Web mode should gracefully recover and return WebsiteWindows
        $result = $this->detector->detect(PlatformMode::Web, $badRequest);

        $this->assertSame(
            RuntimePlatform::WebsiteWindows,
            $result,
            'Web mode should default to WebsiteWindows on exception'
        );

        // Verify it's a valid enum case
        $this->assertInstanceOf(RuntimePlatform::class, $result);
    }

    /**
     * Test that logging occurs when exception is caught
     */
    public function test_exception_is_logged_with_context(): void
    {
        $request = new class extends Request
        {
            public function userAgent()
            {
                throw new \RuntimeException('Test exception message');
            }
        };

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Platform detection failed'
                    && $context['mode'] === 'web'
                    && str_contains($context['error'], 'Test exception message')
                    && isset($context['trace']);
            });

        $this->detector->detect(PlatformMode::Web, $request);
    }

    /**
     * Test null request handling (should not throw exception)
     */
    public function test_null_request_returns_valid_platform(): void
    {
        // Web mode with null request should return WebsiteWindows (default)
        $result = $this->detector->detect(PlatformMode::Web, null);

        $this->assertSame(
            RuntimePlatform::WebsiteWindows,
            $result,
            'Web mode with null request should return WebsiteWindows'
        );

        // Mobile and Desktop modes with null request should work
        $mobileResult = $this->detector->detect(PlatformMode::Mobile, null);
        $this->assertInstanceOf(RuntimePlatform::class, $mobileResult);

        $desktopResult = $this->detector->detect(PlatformMode::Desktop, null);
        $this->assertInstanceOf(RuntimePlatform::class, $desktopResult);
    }

    /**
     * Test various exception types all result in WebsiteWindows default
     */
    public function test_various_exception_types_default_to_website_windows(): void
    {
        $exceptionTypes = [
            new \RuntimeException('Runtime error'),
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic error'),
            new \Exception('Generic exception'),
        ];

        Log::shouldReceive('warning')->times(count($exceptionTypes));

        foreach ($exceptionTypes as $index => $exception) {
            $request = new class($exception) extends Request
            {
                private $exception;

                public function __construct($exception)
                {
                    $this->exception = $exception;
                }

                public function userAgent()
                {
                    throw $this->exception;
                }
            };

            $result = $this->detector->detect(PlatformMode::Web, $request);

            $this->assertSame(
                RuntimePlatform::WebsiteWindows,
                $result,
                'Exception type '.get_class($exception).' should result in WebsiteWindows default'
            );
        }
    }

    /**
     * Test that application continues to work after detection failure
     */
    public function test_application_continues_after_detection_failure(): void
    {
        $badRequest = new class extends Request
        {
            public function userAgent()
            {
                throw new \RuntimeException('First failure');
            }
        };

        Log::shouldReceive('warning')->twice();

        // First detection with exception
        $result1 = $this->detector->detect(PlatformMode::Web, $badRequest);
        $this->assertSame(RuntimePlatform::WebsiteWindows, $result1);

        // Second detection should still work (detector is not broken)
        $result2 = $this->detector->detect(PlatformMode::Web, $badRequest);
        $this->assertSame(RuntimePlatform::WebsiteWindows, $result2);

        // Normal detection should also work
        $goodRequest = Request::create('/test', 'GET');
        $goodRequest->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0)');
        $result3 = $this->detector->detect(PlatformMode::Web, $goodRequest);
        $this->assertInstanceOf(RuntimePlatform::class, $result3);
    }

    /**
     * Test that invalid data structures don't break the detector
     */
    public function test_invalid_data_structures_are_handled(): void
    {
        // Request with unexpected data types
        $weirdRequest = new class extends Request
        {
            public function userAgent()
            {
                // Return unexpected type instead of string
                return ['not', 'a', 'string'];
            }
        };

        Log::shouldReceive('warning')->once();

        $result = $this->detector->detect(PlatformMode::Web, $weirdRequest);

        // Should still return WebsiteWindows as fallback
        $this->assertSame(
            RuntimePlatform::WebsiteWindows,
            $result,
            'Invalid data structure should result in WebsiteWindows default'
        );
    }

    /**
     * Test concurrent exception scenarios in Web mode don't interfere with each other
     */
    public function test_concurrent_exceptions_are_isolated(): void
    {
        Log::shouldReceive('warning')->times(3);

        $badRequest = new class extends Request
        {
            public function userAgent()
            {
                throw new \RuntimeException('Concurrent failure');
            }
        };

        // Simulate concurrent requests in Web mode
        $results = [];

        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->detector->detect(PlatformMode::Web, $badRequest);
        }

        // All should return WebsiteWindows
        foreach ($results as $result) {
            $this->assertSame(
                RuntimePlatform::WebsiteWindows,
                $result,
                'Concurrent exception in Web mode should not affect other detections'
            );
        }
    }

    /**
     * Test that detection never returns null or throws uncaught exception
     */
    public function test_detection_never_returns_null(): void
    {
        $problematicInputs = [
            ['mode' => PlatformMode::Web, 'request' => null],
            ['mode' => PlatformMode::Mobile, 'request' => null],
            ['mode' => PlatformMode::Desktop, 'request' => null],
        ];

        foreach ($problematicInputs as $input) {
            $result = $this->detector->detect($input['mode'], $input['request']);

            $this->assertNotNull(
                $result,
                "Detection should never return null for mode {$input['mode']->value}"
            );

            $this->assertInstanceOf(
                RuntimePlatform::class,
                $result,
                'Detection should always return a RuntimePlatform enum instance'
            );
        }
    }
}
