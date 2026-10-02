<?php

/**
 * Breadcrumb di halaman Sign-In: parent crumb-nya Welcome Home (Beranda),
 * bukan halaman asal klik.
 *
 * Sign In adalah pintu masuk auth dari storefront publik yang terbuka untuk
 * semua user/guest, jadi "halaman asal klik" tidak pernah informatif -- kandidat
 * /welcome/* apa pun hanya menghasilkan label generik "Kembali". "Beranda"
 * selalu benar di mana pun user datang.
 *
 * Lihat SignIn::getBreadcrumbs(). Halaman auth lain (Sign Up, OTP, dst) masih
 * memakai parent dinamis dari HasAuthBreadcrumbs.
 */

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('user'));
});

function breadcrumbNav(string $html): string
{
    $start = strpos($html, '<nav aria-label="breadcrumb"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</nav>', $start);

    if ($end === false) {
        return '';
    }

    return substr($html, $start, $end - $start);
}

it('menampilkan breadcrumb Beranda ke Welcome Home', function (): void {
    $response = $this->get(route('filament.user.auth.login'))
        ->assertOk();

    $nav = breadcrumbNav($response->getContent());

    expect($nav)
        ->toContain('href="'.route('filament.welcome.pages.home').'"')
        ->and($nav)->toContain(__('Beranda'))
        ->and($nav)->toContain(__('Sign In'));
});

it('tetap berparent ke Welcome Home walau url.intended menunjuk detail paket', function (): void {
    $intended = 'http://localhost/welcome/flowerdecorationspackagecatalog/2';

    $response = $this->withSession(['url.intended' => $intended])
        ->get(route('filament.user.auth.login'))
        ->assertOk();

    $nav = breadcrumbNav($response->getContent());

    expect($nav)
        ->toContain('href="'.route('filament.welcome.pages.home').'"')
        ->and($nav)->not->toContain('href="'.$intended.'"');
});

it('tetap berparent ke Welcome Home walau url.intended menunjuk detail produk', function (): void {
    $intended = 'http://localhost/welcome/flowerdecorationscatalog/7';

    $response = $this->withSession(['url.intended' => $intended])
        ->get(route('filament.user.auth.login'))
        ->assertOk();

    $nav = breadcrumbNav($response->getContent());

    expect($nav)
        ->toContain('href="'.route('filament.welcome.pages.home').'"')
        ->and($nav)->not->toContain('href="'.$intended.'"');
});

it('tidak memakai label generik "Kembali" pada halaman Sign In', function (): void {
    $response = $this->get(route('filament.user.auth.login'))
        ->assertOk();

    expect(breadcrumbNav($response->getContent()))->not->toContain(__('Kembali'));
});
