<?php

/**
 * Kamera di halaman tulis ulasan (`CreateReviews`).
 *
 * Halaman ini harus punya UI kamera yang SAMA dengan global search:
 * "Camera Photo", "Camera Video", "Pilih dari Galeri", "Pilih dari File" --
 * modal dan viewfinder-nya persis sama, karena semuanya memakai komponen
 * `TakePicture` yang sama.
 *
 * Yang dikunci di sini:
 *
 *   1. UI kamera itu BENAR-BENAR ada di halaman. Dulu repeater fotonya
 *      `defaultItems(0)`, jadi field `TakePicture`-nya tidak pernah
 *      dirender: tidak ada tombol kamera, tidak ada galeri, tidak ada file.
 *      User cuma bisa mengulas tanpa foto.
 *
 *   2. Halaman punya property `cameraUpload`. Komponen TakePicture
 *      mengunggah video ke property dengan nama itu -- kalau halaman tidak
 *      punya, `$wire.upload()` gagal dan video tidak pernah masuk form.
 *
 *   3. Foto (dari kamera, galeri, atau file) tersimpan danulasannya terkirim.
 *
 * Dua panel diuji: Welcome dan User. Formnya harus sama persis -- kalau
 * panel User masih pakai `FileUpload` biasa, user di sana tetap tidak bisa
 * memotret.
 */

use App\Filament\User\Resources\CatalogResource\Pages\CreateReviews\CreateReviews as UserCreateReviews;
use App\Filament\Welcome\Resources\CatalogResource\Pages\CreateReviews\CreateReviews as WelcomeCreateReviews;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer']);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('customer');

    $this->catalog = Catalog::factory()->create(['is_active' => true]);

    actingAs($this->user, 'web');
});

/**
 * [class CreateReviews, panel id]
 */
dataset('halaman ulasan', [
    'welcome' => [WelcomeCreateReviews::class, 'welcome'],
    'user' => [UserCreateReviews::class, 'user'],
]);

it('halaman tulis ulasan punya tombol kamera, video, galeri, dan file', function (string $class, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    $html = Livewire::test($class, ['record' => $this->catalog->id])->html();

    // Empat sumber, dengan label yang sama seperti global search.
    expect($html)
        ->toContain(__('Camera Photo'))
        ->toContain(__('Camera Video'))
        ->toContain(__('Pilih dari Galeri'))
        ->toContain(__('Pilih dari File'));

    // Viewfinder-nya juga: kamera sungguhan, bukan cuma tombol.
    expect($html)
        ->toContain('getUserMedia')
        ->toContain('startCamera');
})->with('halaman ulasan');

it('halaman tulis ulasan punya property cameraUpload untuk video', function (string $class, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    $component = Livewire::test($class, ['record' => $this->catalog->id]);

    // Komponen TakePicture mengunggah video ke property bernama `cameraUpload`.
    // Kalau tidak ada, upload-nya gagal dan video tidak pernah masuk form.
    expect($component->instance())->toHaveProperty('cameraUpload');
})->with('halaman ulasan');

it('foto yang dipilih masuk ke baris foto yang masih kosong', function (string $class, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    $component = Livewire::test($class, ['record' => $this->catalog->id]);

    $component->set('cameraUpload', \Illuminate\Http\UploadedFile::fake()->create('klip.webm', 40, 'video/webm'));

    $photos = collect($component->get('data')['photos'] ?? [])->values();

    expect($photos)->toHaveCount(1)
        ->and($photos[0]['photo'] ?? null)->toStartWith('review-photos/');
})->with('halaman ulasan');

it('ulasan dengan foto terkirim dan foto ikut tersimpan', function (string $class, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    Livewire::test($class, ['record' => $this->catalog->id])
        ->fillForm([
            'rating'  => 5,
            'title'   => 'Bagus sekali',
            'comment' => 'Dekorasinya rapi dan hasilnya memuaskan.',
            'photos'  => [
                ['photo' => 'review-photos/aaa.jpg'],
            ],
        ])
        ->call('submit');

    $review = Review::query()->latest('id')->first();

    expect($review)->not->toBeNull()
        ->and($review->catalog_id)->toBe($this->catalog->id)
        ->and($review->user_id)->toBe($this->user->id)
        ->and($review->rating)->toBe(5)
        ->and($review->comment)->toBe('Dekorasinya rapi dan hasilnya memuaskan.')
        // Foto pertama ikut ke kolom `photo` supaya kode lama yang hanya
        // membaca kolom itu tetap melihat gambar.
        ->and($review->photo)->toBe('review-photos/aaa.jpg')
        ->and($review->photos)->toBe(['review-photos/aaa.jpg']);
})->with('halaman ulasan');

it('label kamera terbaca utuh dalam bahasa Inggris', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));

    app()->setLocale('en');

    $html = Livewire::test(WelcomeCreateReviews::class, ['record' => $this->catalog->id])->html();

    // Label paket + label milik halaman ini.
    foreach ([
        'Click to capture photo',
        'Your camera will open to take a new photo',
        'Camera Photo',
        'Camera Video',
        'Select from Gallery',
        'Select from Files',
        'Add Photo',
        'Take a photo with the camera, record a video, or pick from the gallery or a file.',
    ] as $label) {
        expect($html)->toContain($label);
    }

    // Tidak boleh ada key mentah paket yang bocor ke layar.
    //
    // `lang/en.json` pernah berisi nilai yang sama persis dengan key-nya,
    // jadi UI menampilkan "filament-take-picture-field::take-picture-field.
    // click_to_capture" -- bukan terjemahannya.
    expect($html)->not->toContain('filament-take-picture-field::');
});
