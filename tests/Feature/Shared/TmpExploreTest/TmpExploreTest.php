<?php

use App\Support\AppPlatform\AppPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use function Pest\Laravel\seed;

/*
 * Temporary exploration: where does the language switcher land for a phone
 * browser (mobile responsive) request?
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed();
});

test('explore placement', function (): void {
    $androidUa = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    $request = Request::create('/home', 'GET');
    $request->headers->set('User-Agent', $androidUa);

    dump([
        'platform' => AppPlatform::detect($request)->value,
        'isAnyMobile' => AppPlatform::isAnyMobile($request),
        'isTablet' => AppPlatform::isTablet($request),
        'switchersBelongInSidebar' => AppPlatform::switchersBelongInSidebar($request),
    ]);

    $html = $this->withHeaders(['User-Agent' => $androidUa])->get('/home')->assertOk()->getContent();

    $langPos = strpos($html, 'x-ref="langPanel"');
    $themePos = strpos($html, 'class="fi-theme-switcher"');
    $sidebarPos = strpos($html, 'fi-sidebar-switchers');
    $topbarPos = strpos($html, 'fi-topbar');

    dump([
        'langPos' => $langPos,
        'themePos' => $themePos,
        'sidebarSwitchersPos' => $sidebarPos,
        'topbarPos' => $topbarPos,
        'langInSidebar' => $langPos !== false && $sidebarPos !== false && $langPos > $sidebarPos,
    ]);
});
