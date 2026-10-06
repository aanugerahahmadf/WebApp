<?php

use App\Filament\User\Auth\CompleteProfile\CompleteProfilePage;
use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Complete Profile harus satu kolom dari atas ke bawah.
 *
 * Alasannya bukan estetika: halaman ini punya label panjang ("Tempat & Tanggal
 * Lahir", "Nama Lengkap", label jenis identitas) yang di grid 2-3 kolom jadi
 * terpotong atau bertumpuk. Satu kolom membuat semua label punya lebar penuh,
 * dan urutan field terbaca sebagai satu daftar.
 *
 * Test ini memanggil form() langsung, bukan lewat HTTP: halaman ini butuh auth
 * DAN middleware EnsureProfileComplete, jadi memanggil lewat Livewire jauh lebih murah
 * dan tidak bergantung pada data user yang lolos gate.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);
});

/**
 * @return array<string, array{columns: int}>
 */
function completeProfileSections(): array
{
    // Dibangun lewat Livewire::test(), bukan newInstanceWithoutConstructor() +
    // form(app(Form::class)): Form mengimplementasikan HasForms yang tidak bisa
    // di-resolve sendiri, jadi harus datang dari komponen yang sudah di-mount.
    $component = Livewire\Livewire::test(CompleteProfileComponent::class);

    return collect($component->instance()->form->getComponents())
        ->filter(fn ($component) => $component instanceof Filament\Forms\Components\Section)
        ->mapWithKeys(fn ($section) => [
            $section->getHeading() => [
                'columns' => $section->getColumns()['default'] ?? 1,
            ],
        ])
        ->all();
}

test('every section on the page is a single column', function (): void {
    actingAs(User::factory()->create(), 'web');

    $sections = completeProfileSections();

    expect($sections)->not->toBeEmpty();

    $wider = array_filter($sections, fn (array $section): bool => $section['columns'] > 1);

    expect($wider)->toBe([], 'section berikut masih lebih dari 1 kolom');
});

test('no field is given a column span of its own', function (): void {
    // columnSpan > 1 di dalam grid 1 kolom tidak berarti apa-apa selain
    // membingungkan pembaca kode: seakan-akan field itu butuh grid yang lebar.
    // Yang boleh tersisa hanya columnSpanFull, karena itu eksplisit "lebar
    // penuh" dan tetap benar kalau someday dikembalikan ke grid banyak kolom.
    $source = file_get_contents(app_path('Livewire/User/CompleteProfileComponent/CompleteProfileComponent.php'));

    $spans = array_filter(
        (array) preg_match_all('/->columnSpan\((\d+)\)/', $source, $matches) !== 0 ? $matches[1] : [],
        fn (int $span): bool => $span > 1,
    );

    expect($spans)->toBe([], "masih ada columnSpan(>1): ".implode(', ', $spans));

    // Dan kolomnya benar-benar 1, bukan 0 / null yang berarti "default panel".
    expect(substr_count($source, '->columns(1)'))->toBe(
        substr_count($source, '->columns('),
        'ada ->columns() yang bukan 1'
    );
});

test('the page still renders for a user who has to complete it', function (string $path): void {
    // pages() terdaftar sebagai CompleteProfilePage dan view-nya memanggil
    // component-nya; test ini menangkap perubahan layout yang membuat halaman
    // tidak bisa dirender sama sekali (mis. schema yang tidak valid).
    actingAs(User::factory()->create(), 'web');

    get($path)->assertOk();
})->with([
    '/user/complete-profile',
]);

test('the heading and layout are untouched by the column change', function (): void {
    $page = (new ReflectionClass(CompleteProfilePage::class))->newInstanceWithoutConstructor();

    expect($page->getHeading())->toBe(__('Lengkapi Profil Anda'))
        ->and($page->hasLogo())->toBeTrue();
});