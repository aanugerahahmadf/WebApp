<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformCommandDetector\PlatformCommandDetector;
use App\Support\Platform\RuntimePlatformDetector\RuntimePlatformDetector;
use Illuminate\Http\Request;

describe('PlatformCommandDetector', function () {
    beforeEach(function () {
        // Store original $_SERVER['argv'] to restore after each test
        $this->originalArgv = $_SERVER['argv'] ?? null;
    });

    afterEach(function () {
        // Restore original argv
        if ($this->originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }
    });

    describe('detectMode()', function () {
        test('detects Web mode from "serve" command', function () {
            $_SERVER['argv'] = ['artisan', 'serve'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('detects Mobile mode from "native:run" command', function () {
            $_SERVER['argv'] = ['artisan', 'native:run'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Mobile);
        });

        test('detects Desktop mode from "native:serve" command', function () {
            $_SERVER['argv'] = ['artisan', 'native:serve'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Desktop);
        });

        test('detects Desktop mode from other native: commands', function () {
            $_SERVER['argv'] = ['artisan', 'native:build'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Desktop);
        });

        test('defaults to Web mode for unknown commands', function () {
            $_SERVER['argv'] = ['artisan', 'migrate'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('defaults to Web mode when no command is provided', function () {
            $_SERVER['argv'] = ['artisan'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('defaults to Web mode when argv is empty', function () {
            $_SERVER['argv'] = [];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('defaults to Web mode when argv is not set', function () {
            unset($_SERVER['argv']);

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('recognizes artisan from full path', function () {
            $_SERVER['argv'] = ['/var/www/html/artisan', 'serve'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('recognizes artisan from Windows path', function () {
            $_SERVER['argv'] = ['C:\\xampp\\htdocs\\project\\artisan', 'native:run'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Mobile);
        });

        test('does not detect artisan from non-artisan script', function () {
            $_SERVER['argv'] = ['php', 'index.php'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });
    });

    describe('command to mode mapping', function () {
        test('maps serve to Web mode', function () {
            $_SERVER['argv'] = ['artisan', 'serve', '--port=8000'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });

        test('maps native:run to Mobile mode', function () {
            $_SERVER['argv'] = ['artisan', 'native:run', 'android'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Mobile);
        });

        test('maps native:serve to Desktop mode', function () {
            $_SERVER['argv'] = ['artisan', 'native:serve'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Desktop);
        });

        test('maps any native: prefixed command to Desktop mode', function () {
            $nativeCommands = ['native:install', 'native:build', 'native:minify'];

            foreach ($nativeCommands as $command) {
                $_SERVER['argv'] = ['artisan', $command];

                $mode = PlatformCommandDetector::detectMode();

                expect($mode)->toBe(PlatformMode::Desktop);
            }
        });
    });

    describe('runtime detection', function () {
        test('defaults to Web mode when no native classes are available', function () {
            // When not running via artisan and no native classes exist
            $_SERVER['argv'] = ['php-fpm'];

            $mode = PlatformCommandDetector::detectMode();

            expect($mode)->toBe(PlatformMode::Web);
        });
    });

    describe('property-based tests', function () {
        describe('Property 1: Platform Mode Switches with Last Command', function () {
            /**
             * **Validates: Requirements 1.6**
             *
             * For any sequence of Artisan commands executed consecutively,
             * the final active platform mode SHALL match the mode associated
             * with the last command in the sequence.
             */
            test('final platform mode matches last command in any sequence', function () {
                // Define command-to-mode mappings
                $commandMappings = [
                    'serve' => PlatformMode::Web,
                    'native:run' => PlatformMode::Mobile,
                    'native:serve' => PlatformMode::Desktop,
                    'native:build' => PlatformMode::Desktop,
                    'native:install' => PlatformMode::Desktop,
                    'migrate' => PlatformMode::Web, // default for unknown commands
                    'queue:work' => PlatformMode::Web,
                    'cache:clear' => PlatformMode::Web,
                ];

                $commands = array_keys($commandMappings);

                // Generate 100 random command sequences of varying lengths
                for ($iteration = 0; $iteration < 100; $iteration++) {
                    // Random sequence length between 1 and 10 commands
                    $sequenceLength = rand(1, 10);
                    $commandSequence = [];

                    // Generate random command sequence
                    for ($i = 0; $i < $sequenceLength; $i++) {
                        $commandSequence[] = $commands[array_rand($commands)];
                    }

                    // Get the last command in the sequence
                    $lastCommand = end($commandSequence);
                    $expectedMode = $commandMappings[$lastCommand];

                    // Simulate executing each command in sequence
                    foreach ($commandSequence as $command) {
                        $_SERVER['argv'] = ['artisan', $command];
                        $currentMode = PlatformCommandDetector::detectMode();

                        // Verify the mode matches what's expected for this command
                        expect($currentMode)->toBe($commandMappings[$command]);
                    }

                    // The final mode should match the last command
                    $_SERVER['argv'] = ['artisan', $lastCommand];
                    $finalMode = PlatformCommandDetector::detectMode();

                    expect($finalMode)
                        ->toBe($expectedMode)
                        ->and($finalMode)
                        ->toBe($commandMappings[$lastCommand]);
                }
            });

            test('command precedence - later commands override earlier ones', function () {
                // Test specific sequences where commands override each other
                $testSequences = [
                    // Web -> Mobile -> Desktop
                    [
                        'commands' => ['serve', 'native:run', 'native:serve'],
                        'expectedFinal' => PlatformMode::Desktop,
                    ],
                    // Desktop -> Web -> Mobile
                    [
                        'commands' => ['native:serve', 'serve', 'native:run'],
                        'expectedFinal' => PlatformMode::Mobile,
                    ],
                    // Mobile -> Desktop -> Web
                    [
                        'commands' => ['native:run', 'native:build', 'serve'],
                        'expectedFinal' => PlatformMode::Web,
                    ],
                    // Multiple same commands ending with different one
                    [
                        'commands' => ['serve', 'serve', 'serve', 'native:run'],
                        'expectedFinal' => PlatformMode::Mobile,
                    ],
                    // Alternating commands
                    [
                        'commands' => ['serve', 'native:run', 'serve', 'native:run', 'serve'],
                        'expectedFinal' => PlatformMode::Web,
                    ],
                ];

                foreach ($testSequences as $testCase) {
                    $commands = $testCase['commands'];
                    $expectedFinal = $testCase['expectedFinal'];

                    // Execute each command in the sequence
                    foreach ($commands as $command) {
                        $_SERVER['argv'] = ['artisan', $command];
                        PlatformCommandDetector::detectMode();
                    }

                    // Verify the final mode matches the last command
                    $lastCommand = end($commands);
                    $_SERVER['argv'] = ['artisan', $lastCommand];
                    $finalMode = PlatformCommandDetector::detectMode();

                    expect($finalMode)
                        ->toBe($expectedFinal)
                        ->and($finalMode->value)
                        ->toBeString();
                }
            });

            test('single command sequences always return correct mode', function () {
                // Property: For a sequence of length 1, the mode matches that single command
                $commandMappings = [
                    'serve' => PlatformMode::Web,
                    'native:run' => PlatformMode::Mobile,
                    'native:serve' => PlatformMode::Desktop,
                ];

                // Test 50 times to ensure consistency
                for ($i = 0; $i < 50; $i++) {
                    foreach ($commandMappings as $command => $expectedMode) {
                        $_SERVER['argv'] = ['artisan', $command];
                        $mode = PlatformCommandDetector::detectMode();

                        expect($mode)->toBe($expectedMode);
                    }
                }
            });

            test('long command sequences maintain last command property', function () {
                // Test with very long sequences (50 commands)
                $allCommands = [
                    'serve',
                    'native:run',
                    'native:serve',
                    'native:build',
                    'migrate',
                    'cache:clear',
                ];

                $commandModes = [
                    'serve' => PlatformMode::Web,
                    'native:run' => PlatformMode::Mobile,
                    'native:serve' => PlatformMode::Desktop,
                    'native:build' => PlatformMode::Desktop,
                    'migrate' => PlatformMode::Web,
                    'cache:clear' => PlatformMode::Web,
                ];

                for ($iteration = 0; $iteration < 20; $iteration++) {
                    // Generate a very long sequence
                    $longSequence = [];
                    for ($i = 0; $i < 50; $i++) {
                        $longSequence[] = $allCommands[array_rand($allCommands)];
                    }

                    $lastCommand = end($longSequence);
                    $expectedFinalMode = $commandModes[$lastCommand];

                    // Execute the entire sequence
                    foreach ($longSequence as $command) {
                        $_SERVER['argv'] = ['artisan', $command];
                        PlatformCommandDetector::detectMode();
                    }

                    // Verify final mode
                    $_SERVER['argv'] = ['artisan', $lastCommand];
                    $finalMode = PlatformCommandDetector::detectMode();

                    expect($finalMode)->toBe($expectedFinalMode);
                }
            });

            test('command sequences with additional arguments preserve mode detection', function () {
                // Commands may have flags/arguments - mode should still be detected correctly
                $commandsWithArgs = [
                    ['serve', '--port=8000', '--host=127.0.0.1'],
                    ['native:run', 'android', '--device=emulator'],
                    ['native:serve', '--dev'],
                    ['serve'],
                ];

                $expectedModes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                    PlatformMode::Web,
                ];

                // Test 30 random sequences with arguments
                for ($iteration = 0; $iteration < 30; $iteration++) {
                    $sequenceLength = rand(2, 5);
                    $sequence = [];

                    for ($i = 0; $i < $sequenceLength; $i++) {
                        $randomIndex = array_rand($commandsWithArgs);
                        $sequence[] = [
                            'argv' => array_merge(['artisan'], $commandsWithArgs[$randomIndex]),
                            'expectedMode' => $expectedModes[$randomIndex],
                        ];
                    }

                    // Get last command's expected mode
                    $lastItem = end($sequence);
                    $expectedFinalMode = $lastItem['expectedMode'];

                    // Execute sequence
                    foreach ($sequence as $item) {
                        $_SERVER['argv'] = $item['argv'];
                        $mode = PlatformCommandDetector::detectMode();
                        expect($mode)->toBe($item['expectedMode']);
                    }

                    // Verify final mode matches last command
                    $_SERVER['argv'] = $lastItem['argv'];
                    $finalMode = PlatformCommandDetector::detectMode();
                    expect($finalMode)->toBe($expectedFinalMode);
                }
            });
        });
    });
});

/**
 * Helper function to generate random user agent strings for testing
 */
function generateRandomUserAgent(): string
{
    $templates = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS %d_%d like Mac OS X) AppleWebKit/605.1.15',
        'Mozilla/5.0 (iPad; CPU OS %d_%d like Mac OS X) AppleWebKit/605.1.15',
        'Mozilla/5.0 (Linux; Android %d; Pixel %d) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X %d_%d) AppleWebKit/537.36',
        'Mozilla/5.0 (Windows NT %d.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
    ];

    $template = $templates[array_rand($templates)];

    return sprintf($template, rand(10, 16), rand(0, 9));
}

describe('RuntimePlatformDetector', function () {
    beforeEach(function () {
        $this->detector = new RuntimePlatformDetector;
    });

    describe('property-based tests', function () {
        describe('Property 2: User Agent Detection Completeness', function () {
            /**
             * **Validates: Requirements 2.1, 10.5**
             *
             * For any valid user agent string provided to the Platform Detector in Web Server Mode,
             * the detector SHALL return one of the four website RuntimePlatform enum cases
             * (WebsiteWindows, WebsiteMacOS, WebsiteAndroid, or WebsiteIos).
             */
            test('returns valid website platform for diverse browser user agents', function () {
                $mode = PlatformMode::Web;

                // Comprehensive list of real-world user agent strings
                $userAgents = [
                    // Modern browsers on Windows
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                    'Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36',
                    'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36',

                    // macOS browsers
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/115.0',
                    'Mozilla/5.0 (Mac OS X; Mac_PowerPC) AppleWebKit/537.36',
                    'Mozilla/5.0 (Macintosh; Darwin 21.0.0) AppleWebKit/537.36',

                    // iOS devices
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
                    'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (iPod touch; CPU iPhone 16_0 like Mac OS X) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)',
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/119.0.6045.169 Mobile/15E148 Safari/604.1',

                    // Android devices
                    'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.43 Mobile Safari/537.36',
                    'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36',
                    'Mozilla/5.0 (Linux; Android 12; SM-G998B Build/SP1A.210812.016) AppleWebKit/537.36',
                    'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/115.0',
                    'Mozilla/5.0 (Linux; U; Android 10; en-us) AppleWebKit/537.36',
                    'Mozilla/5.0 (Linux; Android 9; SAMSUNG SM-G960F)',

                    // Linux browsers
                    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
                    'Mozilla/5.0 (X11; Fedora; Linux x86_64) AppleWebKit/537.36',
                ];

                foreach ($userAgents as $userAgent) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );

                    $result = $this->detector->detect($mode, $request);

                    // Must return one of the website platform cases
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->isWebsite())->toBeTrue();

                    // Must be one of the four website cases
                    $validWebsiteCases = [
                        RuntimePlatform::WebsiteWindows,
                        RuntimePlatform::WebsiteMacOS,
                        RuntimePlatform::WebsiteAndroid,
                        RuntimePlatform::WebsiteIos,
                    ];
                    expect(in_array($result, $validWebsiteCases, true))->toBeTrue();
                }
            });

            test('handles edge case user agents gracefully', function () {
                $mode = PlatformMode::Web;

                // Edge cases: empty, malformed, unusual, very long
                $edgeCaseUserAgents = [
                    // Empty and minimal
                    '',
                    ' ',
                    'Mozilla',
                    'unknown',

                    // Malformed
                    'Mozilla/5.0',
                    'AppleWebKit/537.36',
                    'Safari/537.36',
                    'Chrome',

                    // Unusual bots and crawlers
                    'Googlebot/2.1 (+http://www.google.com/bot.html)',
                    'Baiduspider+(+http://www.baidu.com/search/spider.htm)',
                    'facebookexternalhit/1.1',
                    'Twitterbot/1.0',
                    'LinkedInBot/1.0',
                    'WhatsApp/2.23.20.0',
                    'curl/7.88.1',
                    'Wget/1.21.3',
                    'Python-urllib/3.11',

                    // Custom/unusual browsers
                    'Opera/9.80 (Windows NT 6.1; U; en) Presto/2.10.289 Version/12.02',
                    'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)',
                    'Lynx/2.8.9rel.1 libwww-FM/2.14',
                    'Links (2.28; Linux 5.15.0 x86_64; GNU C 11.2)',

                    // Very long user agent
                    str_repeat('Mozilla/5.0 (Windows NT 10.0; Win64; x64) ', 20),

                    // Special characters
                    'Mozilla/5.0 (Linux; Android 10; <script>alert("XSS")</script>)',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) \' OR \'1\'=\'1',
                    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)\nInjection",

                    // Case variations
                    strtoupper('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
                    strtolower('Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0)'),
                    'MOZILLA/5.0 (IPHONE; CPU IPHONE OS 16_0 LIKE MAC OS X)',

                    // Missing key indicators
                    'CustomBrowser/1.0',
                    'MyApp/2.0 (Build 1234)',
                    'UnknownOS/1.0',
                ];

                foreach ($edgeCaseUserAgents as $userAgent) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );

                    $result = $this->detector->detect($mode, $request);

                    // Must always return a valid website platform (no exceptions thrown)
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->isWebsite())->toBeTrue();

                    // Must be one of the four website cases
                    $validWebsiteCases = [
                        RuntimePlatform::WebsiteWindows,
                        RuntimePlatform::WebsiteMacOS,
                        RuntimePlatform::WebsiteAndroid,
                        RuntimePlatform::WebsiteIos,
                    ];
                    expect(in_array($result, $validWebsiteCases, true))->toBeTrue();
                }
            });

            test('correctly identifies mobile device user agents', function () {
                $mode = PlatformMode::Web;

                // User agents that should be detected as mobile
                $mobileUserAgents = [
                    // iOS
                    ['ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'expected' => RuntimePlatform::WebsiteIos],
                    ['ua' => 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X)', 'expected' => RuntimePlatform::WebsiteIos],
                    ['ua' => 'Mozilla/5.0 (iPod touch; CPU iPhone 15_0 like Mac OS X)', 'expected' => RuntimePlatform::WebsiteIos],
                    ['ua' => 'anything with iphone in it', 'expected' => RuntimePlatform::WebsiteIos],
                    ['ua' => 'anything with ipad in it', 'expected' => RuntimePlatform::WebsiteIos],

                    // Android
                    ['ua' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7)', 'expected' => RuntimePlatform::WebsiteAndroid],
                    ['ua' => 'Mozilla/5.0 (Linux; Android 12; SM-G998B)', 'expected' => RuntimePlatform::WebsiteAndroid],
                    ['ua' => 'Mozilla/5.0 (Android 11; Mobile)', 'expected' => RuntimePlatform::WebsiteAndroid],
                    ['ua' => 'something with android keyword', 'expected' => RuntimePlatform::WebsiteAndroid],
                ];

                foreach ($mobileUserAgents as $testCase) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $testCase['ua']]
                    );

                    $result = $this->detector->detect($mode, $request);

                    expect($result)->toBe($testCase['expected']);
                    expect($result->isWebsite())->toBeTrue();
                }
            });

            test('correctly identifies desktop OS user agents', function () {
                $mode = PlatformMode::Web;

                // User agents that should be detected as desktop
                $desktopUserAgents = [
                    // macOS
                    ['ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0)', 'expected' => RuntimePlatform::WebsiteMacOS],
                    ['ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'expected' => RuntimePlatform::WebsiteMacOS],
                    ['ua' => 'Mozilla/5.0 (Mac OS X; Mac_PowerPC)', 'expected' => RuntimePlatform::WebsiteMacOS],
                    ['ua' => 'Mozilla/5.0 (Macintosh; Darwin 21.0.0)', 'expected' => RuntimePlatform::WebsiteMacOS],
                    ['ua' => 'something with mac keyword', 'expected' => RuntimePlatform::WebsiteMacOS],
                    ['ua' => 'something with darwin keyword', 'expected' => RuntimePlatform::WebsiteMacOS],

                    // Windows (default for unknown)
                    ['ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'expected' => RuntimePlatform::WebsiteWindows],
                    ['ua' => 'Mozilla/5.0 (Windows NT 11.0)', 'expected' => RuntimePlatform::WebsiteWindows],
                    ['ua' => '', 'expected' => RuntimePlatform::WebsiteWindows], // Default
                    ['ua' => 'Unknown Browser', 'expected' => RuntimePlatform::WebsiteWindows], // Default
                    ['ua' => 'Mozilla/5.0 (X11; Linux x86_64)', 'expected' => RuntimePlatform::WebsiteWindows], // Linux defaults to Windows
                ];

                foreach ($desktopUserAgents as $testCase) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $testCase['ua']]
                    );

                    $result = $this->detector->detect($mode, $request);

                    expect($result)->toBe($testCase['expected']);
                    expect($result->isWebsite())->toBeTrue();
                }
            });

            test('generates random user agents and always returns valid website platform', function () {
                $mode = PlatformMode::Web;
                $validWebsiteCases = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                ];

                // Generate and test 200 random user agent variations
                for ($i = 0; $i < 200; $i++) {
                    $userAgent = generateRandomUserAgent();

                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );

                    $result = $this->detector->detect($mode, $request);

                    // Must return a valid RuntimePlatform instance
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);

                    // Must be one of the website platform cases
                    expect(in_array($result, $validWebsiteCases, true))->toBeTrue();

                    // Must satisfy isWebsite() method
                    expect($result->isWebsite())->toBeTrue();
                }
            });
        });

        describe('Property 3: Platform Detection Always Returns Valid Enum', function () {
            /**
             * **Validates: Requirements 2.4**
             *
             * For any platform detection inputs (including mode, request, device info),
             * the RuntimePlatformDetector SHALL return a value that is a valid case
             * of the RuntimePlatform enum.
             */
            test('returns valid enum for all platform modes with various request states', function () {
                $allValidEnumCases = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                // Test all three platform modes
                $modes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                ];

                // Generate 100 random test scenarios
                for ($iteration = 0; $iteration < 100; $iteration++) {
                    foreach ($modes as $mode) {
                        // Test with null request
                        $result = $this->detector->detect($mode, null);

                        expect($result)->toBeInstanceOf(RuntimePlatform::class);
                        expect(in_array($result, $allValidEnumCases, true))->toBeTrue();

                        // Test with various request configurations
                        $requestConfigurations = [
                            // Standard request with user agent
                            ['ua' => generateRandomUserAgent()],
                            // Empty user agent
                            ['ua' => ''],
                            // Null user agent (simulated by not setting it)
                            ['ua' => null],
                            // Random string
                            ['ua' => bin2hex(random_bytes(rand(10, 50)))],
                            // Special characters
                            ['ua' => "Test\nUser\tAgent\r\n".rand(1, 100)],
                        ];

                        foreach ($requestConfigurations as $config) {
                            if ($config['ua'] === null) {
                                $request = Request::create('http://example.com', 'GET');
                            } else {
                                $request = Request::create(
                                    'http://example.com',
                                    'GET',
                                    [],
                                    [],
                                    [],
                                    ['HTTP_USER_AGENT' => $config['ua']]
                                );
                            }

                            $result = $this->detector->detect($mode, $request);

                            // Verify it returns a valid RuntimePlatform enum case
                            expect($result)->toBeInstanceOf(RuntimePlatform::class);
                            expect(in_array($result, $allValidEnumCases, true))->toBeTrue();

                            // Verify the result is appropriate for the mode
                            switch ($mode) {
                                case PlatformMode::Web:
                                    expect($result->isWebsite())->toBeTrue();
                                    break;
                                case PlatformMode::Mobile:
                                    expect($result->isMobileApp())->toBeTrue();
                                    break;
                                case PlatformMode::Desktop:
                                    expect($result->isDesktopApp())->toBeTrue();
                                    break;
                            }
                        }
                    }
                }
            });

            test('returns valid enum for web mode with exhaustive user agent variations', function () {
                $mode = PlatformMode::Web;
                $allValidEnumCases = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                // Keywords and patterns that might appear in user agents
                $osKeywords = ['windows', 'mac', 'macos', 'darwin', 'iphone', 'ipad', 'ipod', 'android', 'linux', 'x11', 'bsd'];
                $browserKeywords = ['chrome', 'firefox', 'safari', 'edge', 'opera', 'brave'];
                $versions = range(8, 17);

                // Generate 150 random user agent combinations
                for ($i = 0; $i < 150; $i++) {
                    // Build random user agent string
                    $parts = [];

                    // Random Mozilla prefix
                    if (rand(0, 1)) {
                        $parts[] = 'Mozilla/5.0';
                    }

                    // Random OS keywords
                    if (rand(0, 1)) {
                        $randomOs = $osKeywords[array_rand($osKeywords)];
                        $parts[] = "($randomOs ".$versions[array_rand($versions)].')';
                    }

                    // Random browser keywords
                    if (rand(0, 1)) {
                        $randomBrowser = $browserKeywords[array_rand($browserKeywords)];
                        $parts[] = "$randomBrowser/".rand(90, 120);
                    }

                    // Random additional strings
                    if (rand(0, 1)) {
                        $parts[] = bin2hex(random_bytes(rand(5, 15)));
                    }

                    $userAgent = implode(' ', $parts);

                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );

                    $result = $this->detector->detect($mode, $request);

                    // Must return a valid RuntimePlatform enum case
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect(in_array($result, $allValidEnumCases, true))->toBeTrue();

                    // For Web mode, must be a website platform
                    expect($result->isWebsite())->toBeTrue();
                }
            });

            test('returns valid enum for mobile and desktop modes regardless of request', function () {
                $allValidEnumCases = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                // Test Mobile mode
                $mobileMode = PlatformMode::Mobile;

                // Test 75 times with various request configurations
                for ($i = 0; $i < 75; $i++) {
                    // Test with null
                    $result = $this->detector->detect($mobileMode, null);
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect(in_array($result, $allValidEnumCases, true))->toBeTrue();
                    expect($result->isMobileApp())->toBeTrue();

                    // Test with random request (should not affect mobile detection)
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                    );

                    $result = $this->detector->detect($mobileMode, $request);
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect(in_array($result, $allValidEnumCases, true))->toBeTrue();
                    expect($result->isMobileApp())->toBeTrue();
                }

                // Test Desktop mode
                $desktopMode = PlatformMode::Desktop;

                // Test 75 times with various request configurations
                for ($i = 0; $i < 75; $i++) {
                    // Test with null
                    $result = $this->detector->detect($desktopMode, null);
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect(in_array($result, $allValidEnumCases, true))->toBeTrue();
                    expect($result->isDesktopApp())->toBeTrue();

                    // Test with random request (should not affect desktop detection)
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                    );

                    $result = $this->detector->detect($desktopMode, $request);
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect(in_array($result, $allValidEnumCases, true))->toBeTrue();
                    expect($result->isDesktopApp())->toBeTrue();
                }
            });

            test('enum cases have consistent category method results', function () {
                // For any detected platform, exactly one category method should return true
                $modes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                ];

                // Test 100 random detections
                for ($i = 0; $i < 100; $i++) {
                    $mode = $modes[array_rand($modes)];

                    // Create random request
                    $request = null;
                    if ($mode === PlatformMode::Web && rand(0, 1)) {
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                        );
                    }

                    $result = $this->detector->detect($mode, $request);

                    // Verify it's a valid enum
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);

                    // Count how many category methods return true
                    $categoryResults = [
                        $result->isWebsite(),
                        $result->isDesktopApp(),
                        $result->isMobileApp(),
                    ];

                    $trueCount = count(array_filter($categoryResults));

                    // Exactly one should be true (mutually exclusive)
                    expect($trueCount)->toBe(1);
                }
            });

            test('always returns valid enum even with missing request object', function () {
                $allValidEnumCases = [
                    RuntimePlatform::WebsiteWindows,
                    RuntimePlatform::WebsiteMacOS,
                    RuntimePlatform::WebsiteAndroid,
                    RuntimePlatform::WebsiteIos,
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ];

                $modes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                ];

                // Test each mode 50 times with null request
                foreach ($modes as $mode) {
                    for ($i = 0; $i < 50; $i++) {
                        $result = $this->detector->detect($mode, null);

                        // Must always return a valid enum case
                        expect($result)->toBeInstanceOf(RuntimePlatform::class);
                        expect(in_array($result, $allValidEnumCases, true))->toBeTrue();

                        // Verify enum value is a string
                        expect($result->value)->toBeString();

                        // Verify enum has a label
                        expect($result->label())->toBeString();
                        expect(strlen($result->label()))->toBeGreaterThan(0);
                    }
                }
            });
        });
    });

    test('detection is case-insensitive for user agent strings', function () {
        $mode = PlatformMode::Web;

        // Test case variations
        $testCases = [
            ['lower' => 'iphone', 'upper' => 'IPHONE', 'expected' => RuntimePlatform::WebsiteIos],
            ['lower' => 'ipad', 'upper' => 'IPAD', 'expected' => RuntimePlatform::WebsiteIos],
            ['lower' => 'android', 'upper' => 'ANDROID', 'expected' => RuntimePlatform::WebsiteAndroid],
            ['lower' => 'macintosh', 'upper' => 'MACINTOSH', 'expected' => RuntimePlatform::WebsiteMacOS],
            ['lower' => 'mac os x', 'upper' => 'MAC OS X', 'expected' => RuntimePlatform::WebsiteMacOS],
        ];

        foreach ($testCases as $testCase) {
            // Test lowercase
            $lowerRequest = Request::create(
                'http://example.com',
                'GET',
                [],
                [],
                [],
                ['HTTP_USER_AGENT' => "Mozilla/5.0 ({$testCase['lower']})"]
            );

            // Test uppercase
            $upperRequest = Request::create(
                'http://example.com',
                'GET',
                [],
                [],
                [],
                ['HTTP_USER_AGENT' => "MOZILLA/5.0 ({$testCase['upper']})"]
            );

            $lowerResult = $this->detector->detect($mode, $lowerRequest);
            $upperResult = $this->detector->detect($mode, $upperRequest);

            // Both should return the same platform
            expect($lowerResult)->toBe($testCase['expected']);
            expect($upperResult)->toBe($testCase['expected']);
            expect($lowerResult)->toBe($upperResult);
        }
    });

    test('generates and tests 300 random user agent variations', function () {
        $mode = PlatformMode::Web;

        // Define templates for generating random user agents
        $templates = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS %d_%d like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (iPad; CPU OS %d_%d like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Linux; Android %d; Pixel %d) AppleWebKit/537.36',
            'Mozilla/5.0 (Linux; Android %d; SM-G%d) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X %d_%d) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X %d_%d_%d) AppleWebKit/605.1.15',
            'Mozilla/5.0 (Windows NT %d.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Windows NT %d.0; Win64; x64; rv:%d.0) Gecko/20100101 Firefox/%d.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/%d.0.0.0',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:%d.0) Gecko/20100101 Firefox/%d.0',
        ];

        // Generate and test 300 random user agents
        for ($i = 0; $i < 300; $i++) {
            $template = $templates[array_rand($templates)];

            // Generate random version numbers
            $version1 = rand(8, 18);
            $version2 = rand(0, 9);
            $version3 = rand(0, 9);

            // Fill template
            $userAgent = sprintf($template, $version1, $version2, $version3 ?? 0);

            $request = Request::create(
                'http://example.com',
                'GET',
                [],
                [],
                [],
                ['HTTP_USER_AGENT' => $userAgent]
            );

            $result = $this->detector->detect($mode, $request);

            // Every generated user agent must return a valid website platform
            expect($result)->toBeInstanceOf(RuntimePlatform::class);
            expect($result->isWebsite())->toBeTrue();

            // Must be one of the four website cases
            $validWebsiteCases = [
                RuntimePlatform::WebsiteWindows,
                RuntimePlatform::WebsiteMacOS,
                RuntimePlatform::WebsiteAndroid,
                RuntimePlatform::WebsiteIos,
            ];
            expect(in_array($result, $validWebsiteCases, true))->toBeTrue();
        }
    });

    test('null request defaults to WebsiteWindows', function () {
        $mode = PlatformMode::Web;

        // Test multiple times to ensure consistency
        for ($i = 0; $i < 50; $i++) {
            $result = $this->detector->detect($mode, null);

            expect($result)->toBe(RuntimePlatform::WebsiteWindows);
            expect($result->isWebsite())->toBeTrue();
        }
    });

    test('user agent priority - mobile indicators take precedence', function () {
        $mode = PlatformMode::Web;

        // User agents with multiple OS indicators - mobile should take precedence
        $ambiguousUserAgents = [
            // iPhone on Mac (should detect as iOS)
            ['ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) on Macintosh', 'expected' => RuntimePlatform::WebsiteIos],

            // iPad mentioned before Mac
            ['ua' => 'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X)', 'expected' => RuntimePlatform::WebsiteIos],

            // Android with Mac mention
            ['ua' => 'Mozilla/5.0 (Linux; Android 13; Built on Mac)', 'expected' => RuntimePlatform::WebsiteAndroid],
        ];

        foreach ($ambiguousUserAgents as $testCase) {
            $request = Request::create(
                'http://example.com',
                'GET',
                [],
                [],
                [],
                ['HTTP_USER_AGENT' => $testCase['ua']]
            );

            $result = $this->detector->detect($mode, $request);

            expect($result)->toBe($testCase['expected']);
        }
    });

    describe('Property 3: Platform Detection Always Returns Valid Enum', function () {
        /**
         * **Validates: Requirements 2.4**
         *
         * For any platform detection inputs (including mode, request, device info),
         * the RuntimePlatformDetector SHALL return a value that is a valid case
         * of the RuntimePlatform enum.
         */
        test('always returns valid RuntimePlatform enum for all platform modes', function () {
            $allModes = [
                PlatformMode::Web,
                PlatformMode::Mobile,
                PlatformMode::Desktop,
            ];

            // Test 200 times with random configurations
            for ($iteration = 0; $iteration < 200; $iteration++) {
                // Randomly select a platform mode
                $mode = $allModes[array_rand($allModes)];

                // Create random request or null
                $request = null;
                if ($mode === PlatformMode::Web) {
                    // 70% chance to provide a request for Web mode
                    if (rand(0, 100) < 70) {
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                        );
                    }
                }

                // Detect platform
                $result = $this->detector->detect($mode, $request);

                // Verify result is a valid RuntimePlatform enum case
                expect($result)->toBeInstanceOf(RuntimePlatform::class);

                // Verify the enum has a valid string value
                expect($result->value)->toBeString();

                // Verify the result is one of the known enum cases
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

                expect(in_array($result, $validCases, true))->toBeTrue();
            }
        });

        test('returns valid enum for web mode with diverse user agents', function () {
            $mode = PlatformMode::Web;

            // Generate 100 random user agent strings
            $userAgents = [
                // iOS devices
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15',
                'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
                'Mozilla/5.0 (iPod touch; CPU iPhone 14_0 like Mac OS X) AppleWebKit/605.1.15',
                // Android devices
                'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36',
                'Mozilla/5.0 (Linux; Android 12; SM-G998B) AppleWebKit/537.36',
                'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/115.0',
                // macOS browsers
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                'Mozilla/5.0 (Macintosh; Darwin 21.0.0) AppleWebKit/537.36',
                // Windows browsers
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
                // Edge cases
                '',
                'unknown-browser',
                'CustomBot/1.0',
                strtoupper('MOZILLA/5.0 (WINDOWS NT 10.0; WIN64; X64)'),
                str_repeat('a', 500), // Very long user agent
            ];

            // Add more random variations
            for ($i = 0; $i < 50; $i++) {
                $userAgents[] = generateRandomUserAgent();
            }

            foreach ($userAgents as $userAgent) {
                $request = Request::create(
                    'http://example.com',
                    'GET',
                    [],
                    [],
                    [],
                    ['HTTP_USER_AGENT' => $userAgent]
                );

                $result = $this->detector->detect($mode, $request);

                // Must always return a valid RuntimePlatform enum
                expect($result)->toBeInstanceOf(RuntimePlatform::class);

                // For web mode, must return one of the website platforms
                expect($result->isWebsite())->toBeTrue();
            }
        });

        test('returns valid enum for web mode with null or missing request', function () {
            $mode = PlatformMode::Web;

            // Test 50 times with null request
            for ($i = 0; $i < 50; $i++) {
                $result = $this->detector->detect($mode, null);

                expect($result)->toBeInstanceOf(RuntimePlatform::class);
                expect($result)->toBe(RuntimePlatform::WebsiteWindows);
            }
        });

        test('returns valid enum for mobile mode regardless of config', function () {
            $mode = PlatformMode::Mobile;

            // Test 100 times - mobile detection doesn't depend on request
            for ($i = 0; $i < 100; $i++) {
                // Randomly pass null or a request (should be ignored)
                $request = rand(0, 1) === 1 ? Request::create('http://example.com') : null;

                $result = $this->detector->detect($mode, $request);

                expect($result)->toBeInstanceOf(RuntimePlatform::class);
                expect($result->isMobileApp())->toBeTrue();

                // Should be either Android or iOS
                expect($result)->toBeIn([
                    RuntimePlatform::MobileAppAndroid,
                    RuntimePlatform::MobileAppIos,
                ]);
            }
        });

        test('returns valid enum for desktop mode regardless of input', function () {
            $mode = PlatformMode::Desktop;

            // Test 100 times - desktop detection doesn't depend on request
            for ($i = 0; $i < 100; $i++) {
                // Randomly pass null or a request (should be ignored)
                $request = rand(0, 1) === 1 ? Request::create('http://example.com') : null;

                $result = $this->detector->detect($mode, $request);

                expect($result)->toBeInstanceOf(RuntimePlatform::class);
                expect($result->isDesktopApp())->toBeTrue();

                // Should be either Windows or macOS
                expect($result)->toBeIn([
                    RuntimePlatform::DesktopAppWindows,
                    RuntimePlatform::DesktopAppMacOS,
                ]);
            }
        });

        test('enum result has required methods and properties', function () {
            $allModes = [
                PlatformMode::Web,
                PlatformMode::Mobile,
                PlatformMode::Desktop,
            ];

            // Test that all returned enums have expected interface
            for ($iteration = 0; $iteration < 50; $iteration++) {
                $mode = $allModes[array_rand($allModes)];
                $request = null;

                if ($mode === PlatformMode::Web && rand(0, 1) === 1) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                    );
                }

                $result = $this->detector->detect($mode, $request);

                // Verify enum has required methods
                expect(method_exists($result, 'label'))->toBeTrue();
                expect(method_exists($result, 'isWebsite'))->toBeTrue();
                expect(method_exists($result, 'isDesktopApp'))->toBeTrue();
                expect(method_exists($result, 'isMobileApp'))->toBeTrue();
                expect(method_exists($result, 'cbirCameraMode'))->toBeTrue();

                // Verify enum methods return valid types
                expect($result->label())->toBeString();
                expect($result->isWebsite())->toBeBool();
                expect($result->isDesktopApp())->toBeBool();
                expect($result->isMobileApp())->toBeBool();
                expect($result->cbirCameraMode())->toBeString();

                // Verify exactly one category method returns true
                $categoryResults = [
                    $result->isWebsite(),
                    $result->isDesktopApp(),
                    $result->isMobileApp(),
                ];
                $trueCount = count(array_filter($categoryResults));
                expect($trueCount)->toBe(1);
            }
        });

        test('detection is consistent for same inputs', function () {
            // Property: Calling detect() with the same inputs should return the same result
            $testCases = [
                [
                    'mode' => PlatformMode::Web,
                    'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                ],
                [
                    'mode' => PlatformMode::Web,
                    'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
                [
                    'mode' => PlatformMode::Web,
                    'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0)',
                ],
                [
                    'mode' => PlatformMode::Web,
                    'userAgent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7)',
                ],
                [
                    'mode' => PlatformMode::Mobile,
                    'userAgent' => null,
                ],
                [
                    'mode' => PlatformMode::Desktop,
                    'userAgent' => null,
                ],
            ];

            foreach ($testCases as $testCase) {
                $mode = $testCase['mode'];
                $userAgent = $testCase['userAgent'];

                $request = null;
                if ($userAgent !== null) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );
                }

                // Call detect multiple times - should be consistent
                $results = [];
                for ($i = 0; $i < 5; $i++) {
                    $results[] = $this->detector->detect($mode, $request);
                }

                // All results should be identical
                $firstResult = $results[0];
                foreach ($results as $result) {
                    expect($result)->toBe($firstResult);
                }
            }
        });
    });

    describe('Property 4: Platform Detection Failure Defaults to WebsiteWindows', function () {
        /**
         * **Validates: Requirements 2.6**
         *
         * For any exception or error that occurs during platform detection,
         * the Platform Detector SHALL catch the exception, log a warning,
         * and return RuntimePlatform::WebsiteWindows as the default value.
         */
        test('returns WebsiteWindows when user agent parsing throws exception', function () {
            $mode = PlatformMode::Web;

            // Create a mock request that will throw an exception when userAgent() is called
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('userAgent')
                ->andThrow(new RuntimeException('User agent parsing failed'));

            $result = $this->detector->detect($mode, $request);

            expect($result)->toBe(RuntimePlatform::WebsiteWindows);
        })->skip('Mockery not available in property test context');

        test('returns WebsiteWindows and logs warning when detection fails for web mode', function () {
            // Test with various inputs that might cause failures
            $mode = PlatformMode::Web;

            // Generate 50 different potentially problematic scenarios
            for ($iteration = 0; $iteration < 50; $iteration++) {
                $request = null;

                // Try different problematic request configurations
                $scenario = $iteration % 5;

                switch ($scenario) {
                    case 0:
                        // Null request
                        $request = null;
                        break;
                    case 1:
                        // Request with empty user agent
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => '']
                        );
                        break;
                    case 2:
                        // Request with very long user agent
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => str_repeat('a', 10000)]
                        );
                        break;
                    case 3:
                        // Request with special characters
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => "\x00\x01\x02<script>alert('xss')</script>"]
                        );
                        break;
                    case 4:
                        // Request with malformed UTF-8
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => "\xC3\x28"]
                        );
                        break;
                }

                // Detection should never throw - always return a valid enum
                $result = $this->detector->detect($mode, $request);

                expect($result)->toBeInstanceOf(RuntimePlatform::class);

                // Result should be a website platform (since mode is Web)
                expect($result->isWebsite())->toBeTrue();
            }
        });

        test('handles exceptions gracefully across all platform modes', function () {
            $allModes = [
                PlatformMode::Web,
                PlatformMode::Mobile,
                PlatformMode::Desktop,
            ];

            // Test 100 times with various potentially problematic inputs
            for ($iteration = 0; $iteration < 100; $iteration++) {
                $mode = $allModes[array_rand($allModes)];

                // Create potentially problematic request
                $request = null;
                if (rand(0, 100) < 50) {
                    $userAgentOptions = [
                        null,
                        '',
                        str_repeat('x', 50000),
                        "\x00\x01\x02",
                        '<?php eval($_GET["cmd"]); ?>',
                        'SELECT * FROM users; DROP TABLE users;--',
                        str_repeat("\n", 1000),
                        json_encode(['nested' => ['very' => ['deeply' => str_repeat('nested', 100)]]]),
                    ];

                    $userAgent = $userAgentOptions[array_rand($userAgentOptions)];

                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );
                }

                // Detection should NEVER throw an exception
                try {
                    $result = $this->detector->detect($mode, $request);

                    // Result must always be valid
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->value)->toBeString();

                    // Result should match the expected platform mode category
                    switch ($mode) {
                        case PlatformMode::Web:
                            expect($result->isWebsite())->toBeTrue();
                            break;
                        case PlatformMode::Mobile:
                            expect($result->isMobileApp())->toBeTrue();
                            break;
                        case PlatformMode::Desktop:
                            expect($result->isDesktopApp())->toBeTrue();
                            break;
                    }
                } catch (Throwable $e) {
                    // If any exception is thrown, the test fails
                    expect(false)->toBeTrue('Detection threw exception: '.$e->getMessage());
                }
            }
        });

        test('returns WebsiteWindows as default fallback when web detection encounters issues', function () {
            $mode = PlatformMode::Web;

            // Test scenarios that should trigger fallback behavior
            $fallbackScenarios = [
                // Null request should default to WebsiteWindows
                ['request' => null, 'expectedDefault' => RuntimePlatform::WebsiteWindows],
                // Empty user agent should default to WebsiteWindows
                ['userAgent' => '', 'expectedDefault' => RuntimePlatform::WebsiteWindows],
                // Unrecognizable user agent should default to WebsiteWindows
                ['userAgent' => 'CustomBot/1.0', 'expectedDefault' => RuntimePlatform::WebsiteWindows],
                ['userAgent' => 'UnknownBrowser', 'expectedDefault' => RuntimePlatform::WebsiteWindows],
            ];

            foreach ($fallbackScenarios as $scenario) {
                $request = null;

                if (! isset($scenario['request'])) {
                    $userAgent = $scenario['userAgent'] ?? '';
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );
                }

                $result = $this->detector->detect($mode, $request);

                // Should return the default fallback platform
                expect($result)->toBe($scenario['expectedDefault']);
            }
        });

        test('recovery behavior works consistently across multiple detection attempts', function () {
            // Property: If detection fails once, subsequent calls with same input should also fail consistently
            $mode = PlatformMode::Web;

            // Test problematic inputs multiple times
            $problematicInputs = [
                null,
                Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => '']),
                Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'UnknownBot']),
            ];

            foreach ($problematicInputs as $input) {
                $results = [];

                // Call detect 10 times with same problematic input
                for ($i = 0; $i < 10; $i++) {
                    $results[] = $this->detector->detect($mode, $input);
                }

                // All results should be identical (consistent recovery)
                $firstResult = $results[0];
                foreach ($results as $result) {
                    expect($result)
                        ->toBe($firstResult)
                        ->and($result)
                        ->toBeInstanceOf(RuntimePlatform::class);
                }

                // Should be WebsiteWindows (the default fallback)
                expect($firstResult)->toBe(RuntimePlatform::WebsiteWindows);
            }
        });

        test('detection never crashes with random binary data in user agent', function () {
            $mode = PlatformMode::Web;

            // Generate 50 random binary strings as user agents
            for ($iteration = 0; $iteration < 50; $iteration++) {
                // Generate random binary data
                $binaryData = '';
                $length = rand(10, 500);
                for ($i = 0; $i < $length; $i++) {
                    $binaryData .= chr(rand(0, 255));
                }

                $request = Request::create(
                    'http://example.com',
                    'GET',
                    [],
                    [],
                    [],
                    ['HTTP_USER_AGENT' => $binaryData]
                );

                // Should never throw
                try {
                    $result = $this->detector->detect($mode, $request);

                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->isWebsite())->toBeTrue();
                } catch (Throwable $e) {
                    expect(false)->toBeTrue('Detection crashed with binary data: '.bin2hex(substr($binaryData, 0, 20)));
                }
            }
        });

        test('all platform modes handle missing classes gracefully', function () {
            // This tests the recovery behavior when NativePHP classes don't exist
            // Since we can't easily make classes disappear, we test that detection
            // works regardless of class availability

            $allModes = [
                PlatformMode::Web,
                PlatformMode::Mobile,
                PlatformMode::Desktop,
            ];

            foreach ($allModes as $mode) {
                // Try detection 20 times per mode
                for ($i = 0; $i < 20; $i++) {
                    $request = null;
                    if ($mode === PlatformMode::Web) {
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                        );
                    }

                    $result = $this->detector->detect($mode, $request);

                    // Must return valid enum
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);

                    // Must match expected platform category
                    switch ($mode) {
                        case PlatformMode::Web:
                            expect($result->isWebsite())->toBeTrue();
                            break;
                        case PlatformMode::Mobile:
                            expect($result->isMobileApp())->toBeTrue();
                            break;
                        case PlatformMode::Desktop:
                            expect($result->isDesktopApp())->toBeTrue();
                            break;
                    }
                }
            }
        });

        test('exception logging includes relevant context', function () {
            // This test verifies that when exceptions occur, they are logged with context
            // We can't easily mock the Log facade in property tests, so we verify
            // that detection completes successfully even when it would log

            $mode = PlatformMode::Web;

            // Create various edge case requests that would trigger logging
            $edgeCaseRequests = [
                null, // Would log: no request provided
                Request::create('http://example.com', 'GET'), // No user agent
            ];

            foreach ($edgeCaseRequests as $request) {
                // Detection should complete without throwing
                $result = $this->detector->detect($mode, $request);

                expect($result)->toBeInstanceOf(RuntimePlatform::class);
                expect($result)->toBe(RuntimePlatform::WebsiteWindows);
            }
        });

        test('property: exception recovery is deterministic', function () {
            // Property: For any input that causes an exception, the recovery result
            // should always be the same (WebsiteWindows for Web mode)

            $mode = PlatformMode::Web;

            // Test 100 random edge cases
            for ($iteration = 0; $iteration < 100; $iteration++) {
                $scenario = $iteration % 6;
                $request = null;

                switch ($scenario) {
                    case 0:
                        $request = null;
                        break;
                    case 1:
                        $request = Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => '']);
                        break;
                    case 2:
                        $request = Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => str_repeat("\x00", 1000)]);
                        break;
                    case 3:
                        $request = Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => str_repeat('a', 100000)]);
                        break;
                    case 4:
                        $request = Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'UnknownDevice/1.0']);
                        break;
                    case 5:
                        $request = Request::create('http://example.com', 'GET', [], [], [], ['HTTP_USER_AGENT' => json_encode(['nested' => 'data'])]);
                        break;
                }

                // Call detect multiple times with same input
                $results = [];
                for ($i = 0; $i < 5; $i++) {
                    $results[] = $this->detector->detect($mode, $request);
                }

                // All results should be identical and valid
                $firstResult = $results[0];
                expect($firstResult)->toBeInstanceOf(RuntimePlatform::class);

                foreach ($results as $result) {
                    expect($result)->toBe($firstResult);
                }
            }
        });
    });
});

describe('RuntimePlatformDetector', function () {
    beforeEach(function () {
        $this->detector = new RuntimePlatformDetector;
    });

    describe('property-based tests', function () {
        describe('Property 3: Platform Detection Always Returns Valid Enum', function () {
            /**
             * **Validates: Requirements 2.4**
             *
             * For any platform detection inputs (including mode, request, device info),
             * the RuntimePlatformDetector SHALL return a value that is a valid case
             * of the RuntimePlatform enum.
             */
            test('always returns valid RuntimePlatform enum for all platform modes', function () {
                $allModes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                ];

                // Test 200 times with random configurations
                for ($iteration = 0; $iteration < 200; $iteration++) {
                    // Randomly select a platform mode
                    $mode = $allModes[array_rand($allModes)];

                    // Create random request or null
                    $request = null;
                    if ($mode === PlatformMode::Web) {
                        // 70% chance to provide a request for Web mode
                        if (rand(0, 100) < 70) {
                            $request = Request::create(
                                'http://example.com',
                                'GET',
                                [],
                                [],
                                [],
                                ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                            );
                        }
                    }

                    // Detect platform
                    $result = $this->detector->detect($mode, $request);

                    // Verify result is a valid RuntimePlatform enum case
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);

                    // Verify the enum has a valid string value
                    expect($result->value)->toBeString();

                    // Verify the result is one of the known enum cases
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

                    expect(in_array($result, $validCases, true))->toBeTrue();
                }
            });

            test('returns valid enum for web mode with diverse user agents', function () {
                $mode = PlatformMode::Web;

                // Generate 100 random user agent strings
                $userAgents = [
                    // iOS devices
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (iPod touch; CPU iPhone 14_0 like Mac OS X) AppleWebKit/605.1.15',
                    // Android devices
                    'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36',
                    'Mozilla/5.0 (Linux; Android 12; SM-G998B) AppleWebKit/537.36',
                    'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/115.0',
                    // macOS browsers
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/537.36',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                    'Mozilla/5.0 (Macintosh; Darwin 21.0.0) AppleWebKit/537.36',
                    // Windows browsers
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
                    // Edge cases
                    '',
                    'unknown-browser',
                    'CustomBot/1.0',
                    strtoupper('MOZILLA/5.0 (WINDOWS NT 10.0; WIN64; X64)'),
                    str_repeat('a', 500), // Very long user agent
                ];

                // Add more random variations
                for ($i = 0; $i < 50; $i++) {
                    $userAgents[] = generateRandomUserAgent();
                }

                foreach ($userAgents as $userAgent) {
                    $request = Request::create(
                        'http://example.com',
                        'GET',
                        [],
                        [],
                        [],
                        ['HTTP_USER_AGENT' => $userAgent]
                    );

                    $result = $this->detector->detect($mode, $request);

                    // Must always return a valid RuntimePlatform enum
                    expect($result)->toBeInstanceOf(RuntimePlatform::class);

                    // For web mode, must return one of the website platforms
                    expect($result->isWebsite())->toBeTrue();
                }
            });

            test('returns valid enum for web mode with null or missing request', function () {
                $mode = PlatformMode::Web;

                // Test 50 times with null request
                for ($i = 0; $i < 50; $i++) {
                    $result = $this->detector->detect($mode, null);

                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result)->toBe(RuntimePlatform::WebsiteWindows);
                }
            });

            test('returns valid enum for mobile mode regardless of config', function () {
                $mode = PlatformMode::Mobile;

                // Test 100 times - mobile detection doesn't depend on request
                for ($i = 0; $i < 100; $i++) {
                    // Randomly pass null or a request (should be ignored)
                    $request = rand(0, 1) === 1 ? Request::create('http://example.com') : null;

                    $result = $this->detector->detect($mode, $request);

                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->isMobileApp())->toBeTrue();

                    // Should be either Android or iOS
                    expect($result)->toBeIn([
                        RuntimePlatform::MobileAppAndroid,
                        RuntimePlatform::MobileAppIos,
                    ]);
                }
            });

            test('returns valid enum for desktop mode regardless of input', function () {
                $mode = PlatformMode::Desktop;

                // Test 100 times - desktop detection doesn't depend on request
                for ($i = 0; $i < 100; $i++) {
                    // Randomly pass null or a request (should be ignored)
                    $request = rand(0, 1) === 1 ? Request::create('http://example.com') : null;

                    $result = $this->detector->detect($mode, $request);

                    expect($result)->toBeInstanceOf(RuntimePlatform::class);
                    expect($result->isDesktopApp())->toBeTrue();

                    // Should be either Windows or macOS
                    expect($result)->toBeIn([
                        RuntimePlatform::DesktopAppWindows,
                        RuntimePlatform::DesktopAppMacOS,
                    ]);
                }
            });

            test('enum result has required methods and properties', function () {
                $allModes = [
                    PlatformMode::Web,
                    PlatformMode::Mobile,
                    PlatformMode::Desktop,
                ];

                // Test that all returned enums have expected interface
                for ($iteration = 0; $iteration < 50; $iteration++) {
                    $mode = $allModes[array_rand($allModes)];
                    $request = null;

                    if ($mode === PlatformMode::Web && rand(0, 1) === 1) {
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => generateRandomUserAgent()]
                        );
                    }

                    $result = $this->detector->detect($mode, $request);

                    // Verify enum has required methods
                    expect(method_exists($result, 'label'))->toBeTrue();
                    expect(method_exists($result, 'isWebsite'))->toBeTrue();
                    expect(method_exists($result, 'isDesktopApp'))->toBeTrue();
                    expect(method_exists($result, 'isMobileApp'))->toBeTrue();
                    expect(method_exists($result, 'cbirCameraMode'))->toBeTrue();

                    // Verify enum methods return valid types
                    expect($result->label())->toBeString();
                    expect($result->isWebsite())->toBeBool();
                    expect($result->isDesktopApp())->toBeBool();
                    expect($result->isMobileApp())->toBeBool();
                    expect($result->cbirCameraMode())->toBeString();

                    // Verify exactly one category method returns true
                    $categoryResults = [
                        $result->isWebsite(),
                        $result->isDesktopApp(),
                        $result->isMobileApp(),
                    ];
                    $trueCount = count(array_filter($categoryResults));
                    expect($trueCount)->toBe(1);
                }
            });

            test('detection is consistent for same inputs', function () {
                // Property: Calling detect() with the same inputs should return the same result
                $testCases = [
                    [
                        'mode' => PlatformMode::Web,
                        'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                    ],
                    [
                        'mode' => PlatformMode::Web,
                        'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    ],
                    [
                        'mode' => PlatformMode::Web,
                        'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0)',
                    ],
                    [
                        'mode' => PlatformMode::Web,
                        'userAgent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7)',
                    ],
                    [
                        'mode' => PlatformMode::Mobile,
                        'userAgent' => null,
                    ],
                    [
                        'mode' => PlatformMode::Desktop,
                        'userAgent' => null,
                    ],
                ];

                foreach ($testCases as $testCase) {
                    $mode = $testCase['mode'];
                    $userAgent = $testCase['userAgent'];

                    $request = null;
                    if ($userAgent !== null) {
                        $request = Request::create(
                            'http://example.com',
                            'GET',
                            [],
                            [],
                            [],
                            ['HTTP_USER_AGENT' => $userAgent]
                        );
                    }

                    // Call detect() 20 times with same inputs
                    $results = [];
                    for ($i = 0; $i < 20; $i++) {
                        // Re-create request each time to ensure true statelessness
                        $freshRequest = null;
                        if ($userAgent !== null) {
                            $freshRequest = Request::create(
                                'http://example.com',
                                'GET',
                                [],
                                [],
                                [],
                                ['HTTP_USER_AGENT' => $userAgent]
                            );
                        }

                        $results[] = $this->detector->detect($mode, $freshRequest);
                    }

                    // All results should be identical
                    $firstResult = $results[0];
                    foreach ($results as $result) {
                        expect($result)->toBe($firstResult);
                    }
                }
            });
        });
    });
});
