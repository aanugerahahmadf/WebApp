<?php

/**
 * Tombol Unduh PDF untuk laporan di halaman Messages.
 *
 * Tiga lapis yang harus benar:
 *
 *   1. Route /welcome/reports/{report}/pdf TIDAK memakai middleware auth.
 *      Panel Welcome melayani tamu, dan laporan bug bisa dibuat tamu. Dengan
 *      auth, tamu yang melapor mendarat di halaman login, bukan PDF-nya --
 *      dan window.location.assign() dari Livewire ikut ter-follow ke sana.
 *
 *   2. Karena auth tidak lagi refinance_route, kepemilikan diperiksa di
 *      controller lewat GuestIdentity. Pelapor boleh; orang lain 403.
 *
 *   3. View bisa tahu bahwa laporan sudah dikirim. Tabel reports tidak punya
 *      kolom inbox_id, jadi id-nya dicatat di meta percakapan oleh aksi
 *      csReportForm -- pola yang sama dengan consultation_forms.
 */

use App\Enums\ReportStatus\ReportStatus;
use App\Models\Report\Report;
use App\Services\GuestIdentity\GuestIdentity;
use App\Models\User\User;

/*
 * Route
 */

/**
 * Middleware yang benar-benar berlaku untuk sebuah route, sudah di-resolve.
 */
function middlewareFor(string $routeName): array
{
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->getName() === $routeName);

    expect($route)->not->toBeNull();

    return array_map(
        fn ($m): string => is_string($m) ? $m : $m::class,
        $route->gatherMiddleware()
    );
}

it('tidak memakai middleware auth untuk PDF laporan di panel Welcome', function (): void {
    // Kalau auth kembali ditambahkan, tamu tidak bisa mengunduh PDF-nya lagi.
    $middleware = collect(middlewareFor('welcome.reports.pdf'));

    expect($middleware->filter(
        fn (string $m): bool => str_contains($m, 'Authenticate') || $m === 'auth'
    ))->toBeEmpty();
});

it('mempertahankan URL dan nama route yang sama', function (): void {
    // Nama route tidak boleh berubah: blade Welcome memakai welcome.reports.pdf.
    expect(route('welcome.reports.pdf', 1))
        ->toContain('/welcome/reports/1/pdf');
});

/*
 * Kepemilikan -- tamu
 */

it('mengizinkan tamu mengunduh PDF laporannya sendiri', function (): void {
    $guestId = 'guest-'.fake()->uuid();
    $guest = GuestIdentity::resolve($guestId);

    $report = Report::create([
        'user_id' => $guest->id,
        'category' => 'bug_report',
        'reason' => 'Tombol tidak berfungsi',
        'description' => 'Detail laporan',
        'status' => ReportStatus::OPEN,
    ]);

    $this->withCookie(GuestIdentity::COOKIE, $guestId);

    // Singleton: harus dilepas supaya request berikutnya membaca cookie baru.
    $this->app->forgetInstance(GuestIdentity::class);

    $this->get(route('welcome.reports.pdf', $report))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('menolak tamu mengunduh PDF milik orang lain', function (): void {
    $mine = GuestIdentity::resolve('guest-'.fake()->uuid());
    $someoneElse = GuestIdentity::resolve('guest-'.fake()->uuid());

    $theirReport = Report::create([
        'user_id' => $someoneElse->id,
        'category' => 'bug_report',
        'reason' => 'Rahasia orang lain',
        'description' => 'Bukan untuk dilihat',
        'status' => ReportStatus::OPEN,
    ]);

    $this->withCookie(GuestIdentity::COOKIE, 'guest-'.fake()->uuid());
    $this->app->forgetInstance(GuestIdentity::class);

    $this->get(route('welcome.reports.pdf', $theirReport))->assertForbidden();
});

it('menolak tamu tanpa cookie identitas', function (): void {
    $report = Report::create([
        'user_id' => GuestIdentity::resolve('guest-'.fake()->uuid())->id,
        'category' => 'bug_report',
        'reason' => 'Laporan orang lain',
        'description' => '-',
        'status' => ReportStatus::OPEN,
    ]);

    $this->app->forgetInstance(GuestIdentity::class);

    $this->get(route('welcome.reports.pdf', $report))->assertForbidden();
});

/*
 * Kepemilikan -- yang sudah login
 */

it('mengizinkan pengguna mengunduh laporannya sendiri', function (): void {
    $user = User::factory()->create();

    $report = Report::create([
        'user_id' => $user->id,
        'category' => 'bug_report',
        'reason' => 'Masalah login',
        'description' => 'Detail',
        'status' => ReportStatus::OPEN,
    ]);

    $this->actingAs($user)
        ->get(route('welcome.reports.pdf', $report))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('menolak pengguna mengunduh laporan milik orang lain', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $theirReport = Report::create([
        'user_id' => $other->id,
        'category' => 'bug_report',
        'reason' => 'Laporan orang lain',
        'description' => '-',
        'status' => ReportStatus::OPEN,
    ]);

    $this->actingAs($user)
        ->get(route('welcome.reports.pdf', $theirReport))
        ->assertForbidden();
});

it('route user tetap memakai middleware auth', function (): void {
    // Panel User hanya untuk yang sudah login, jadi route-nya tidak boleh
    // ikut jadi bebas auth karena perubahan di panel Welcome.
    $middleware = middlewareFor('user.reports.pdf');

    expect(collect($middleware)->filter(
        fn (string $m): bool => str_contains($m, 'Authenticate') || $m === 'auth'
    ))->not->toBeEmpty();
});

/*
 * View
 */

it('menampilkan tombol Unduh PDF di view Messages panel Welcome dan User', function (string $panel): void {
    // `$panel` adalah PANEL ID ('welcome'/'user'), sedangkan folder view-nya
    // huruf kapital (Welcome/User) -- itu memang nama foldernya di
    // resources/views.
    //
    // Dua hal itu dipisah karena Windows tidak membedakan huruf besar-kecil
    // pada filesystem, jadi path huruf kecil tetap ketemu di laptop dan test
    // hijau. Di Linux (runner CI) tidak begitu, dan file_get_contents gagal
    // dengan "Failed to open stream: No such file or directory" -- test jadi
    // merah hanya di CI. Nama route tetap huruf kecil karena memang begitu
    // oleh Filament.
    $directory = ucfirst($panel);

    $path = resource_path("views/{$directory}/livewire/messages/messages/messages.blade.php");
    $src = (string) file_get_contents($path);
    $routeName = "{$panel}.reports.pdf";

    // Tombol hanya boleh muncul kalau ada laporan di meta percakapan.
    expect($src)->toContain('$mySubmittedReport')
        ->and($src)->toContain('$hasSubmittedReport')
        ->and($src)->toContain("__('Unduh PDF')");

    // Blade memanggil route() secara dinamis, jadi yang diperiksa adalah
    // nama route -- bukan URL hasilnya.
    expect($src)->toContain("route('{$routeName}', \$mySubmittedReport['id'])");
})->with(['welcome', 'user']);