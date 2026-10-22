<?php

use App\Filament\Admin\Exports\CategoryExporter\CategoryExporter;
use App\Filament\Admin\Pages\DataExportPage\DataExportPage;
use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Services\DataExportService\DataExportService;
use App\Support\ExportText\ExportText;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    // Role untuk panel admin (guard `admin`) DAN untuk user di bawah (guard
    // `web`) -- `User::assignRole()` mencari di guard model itu sendiri.
    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    // Route yang diuji ada di `/admin/...` dan dijaga middleware `SuperAdmin`.
    // Middleware itu menolak apa pun yang bukan model `Admin` -- dengan `403`,
    // bukan redirect.
    //
    // Test lama memakai `User::factory()` + `actingAs($user, 'web')`, yang
    // selalu berakhir 403. Gejalanya terlihat seperti route-nya yang rusak,
    // padahal tidak ada yang salah pada route.
    $this->admin = Admin::factory()->superAdmin()->create([
        'email_verified_at' => now(),
        'active_status'     => true,
    ]);

    actingAs($this->admin, 'admin');

    // `DataExportService::buildPdf()` / `buildWorkbook()` menerima SIAPA
    // pun yang menjemduh (guard `admin` -> `Admin`, guard `web` -> `User`).
    // Test ini butuh KEDUA actor: admin untuk guard `admin` (middleware
    // `SuperAdmin`), dan user untuk argumen service.
    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    $this->exportDir = storage_path('app/private/data-exports');
    $this->beforeFiles = is_dir($this->exportDir) ? File::files($this->exportDir) : [];
});

afterEach(function (): void {
    // Bersihkan file yang dibuat service selama test (baris DB di-rollback otomatis).
    $after = is_dir($this->exportDir) ? File::files($this->exportDir) : [];
    $before = collect($this->beforeFiles)->map(fn ($f) => $f->getPathname())->all();
    foreach ($after as $file) {
        if (! in_array($file->getPathname(), $before, true)) {
            @unlink($file->getPathname());
        }
    }
});

test('buildPdf menghasilkan satu file PDF valid', function (): void {
    $path = DataExportService::buildPdf(['categories', 'vouchers'], $this->user);

    expect($path)->toBeString()
        ->and(File::exists($path))->toBeTrue()
        ->and(pathinfo($path, PATHINFO_EXTENSION))->toBe('pdf')
        ->and(substr(File::get($path), 0, 5))->toBe('%PDF-');
});

test('buildWorkbook tetap menghasilkan XLSX valid', function (): void {
    $path = DataExportService::buildWorkbook(['categories'], $this->user);

    expect(File::exists($path))->toBeTrue()
        ->and(pathinfo($path, PATHINFO_EXTENSION))->toBe('xlsx')
        ->and(substr(File::get($path), 0, 2))->toBe('PK');
});

test('admin boleh menjemduh: actor-nya dicatat apa adanya di tabel exports', function (): void {
    // Regression: halaman ini dulu melempar TypeError --
    // `DataExportService::buildWorkbook()` mewajibkan `User`, padahal panel
    // admin selalu mengembalikan model `Admin` (tabel `admins` terpisah dari
    // `users`). Gejalanya: notifikasi "Gagal membuat arsip." tanpa unduhan.
    //
    // Perbaikannya bukan "akui admin sebagai user" (itu menulis `admins.id`
    // ke kolom yang maknanya `users.id`), tapi relasi `exports.user` yang
    // polimorfik: `user_type` menyimpan model aktualnya.
    $path = DataExportService::buildWorkbook(['categories'], $this->admin);

    expect(File::exists($path))->toBeTrue();

    $export = Export::query()
        ->where('exporter', CategoryExporter::class)
        ->latest('id')
        ->first();

    expect($export)->not->toBeNull()
        ->and($export->user_type)->toBe((new Admin)->getMorphClass())
        ->and((int) $export->user_id)->toBe((int) $this->admin->getKey())
        // Relasinya harus mengembalikan model yang benar, bukan shopper lain.
        ->and($export->user)->toBeInstanceOf(Admin::class)
        ->and((int) $export->user->getKey())->toBe((int) $this->admin->getKey());
});

test('shopper juga boleh tercatat sebagai pembuat export', function (): void {
    $path = DataExportService::buildWorkbook(['categories'], $this->user);

    expect(File::exists($path))->toBeTrue();

    $export = Export::query()
        ->where('exporter', CategoryExporter::class)
        ->latest('id')
        ->first();

    expect($export->user_type)->toBe((new User)->getMorphClass())
        ->and((int) $export->user_id)->toBe((int) $this->user->getKey())
        ->and($export->user)->toBeInstanceOf(User::class);
});

test('teks export bebas underscore dan slash', function (): void {
    // Aturan main: TIDAK ADA "_" DAN TIDAK ADA "/" di teks mana pun --
    // di XLSX maupun di PDF.
    expect(ExportText::heading('full_name'))->toBe('Full Name')
        ->and(ExportText::heading('created_at'))->toBe('Created At')
        ->and(ExportText::heading('id'))->toBe('ID')
        ->and(ExportText::heading('ktp_photo_url'))->toBe('KTP Photo URL')
        // Label yang sudah rapi: kapitalisasinya dihormati ("IP" jangan
        // jadi "Ip").
        ->and(ExportText::heading('IP Login Terakhir'))->toBe('IP Login Terakhir')
        ->and(ExportText::heading('Nama Lengkap'))->toBe('Nama Lengkap');

    // Nilai mentah apa pun harus bersih.
    expect(ExportText::value('super_admin'))->toBe('Super Admin')
        ->and(ExportText::value('admin/super_admin'))->toBe('Admin Super Admin')
        ->and(ExportText::value('paket-dekorasi'))->toBe('Paket Dekorasi')
        ->and(ExportText::value('2026-10-02'))->toBe('02 Oktober 2026, 00.00')
        // Kode (ada angkanya) bukan label -- kapitalisasinya jangan diubah,
        // dan tanda hubungnya tetap (bukan "_" atau "/").
        ->and(ExportText::value('INV-2026-001'))->toBe('INV-2026-001')
        ->and(ExportText::value('https://web.id/media/avatars/1_1792_Onx6Q.jpg'))->not->toContain('/')
        ->and(ExportText::value('https://web.id/media/avatars/1_1792_Onx6Q.jpg'))->not->toContain('_')
        ->and(ExportText::value('user@mail.com'))->not->toContain('_')
        ->and(ExportText::value('user@mail.com'))->not->toContain('/')
        ->and(ExportText::value('{"full_name":"Budi","user_id":7}'))->not->toContain('_')
        ->and(ExportText::value('{"full_name":"Budi","user_id":7}'))->not->toContain('/')
        ->and(ExportText::value('{"full_name":"Budi","user_id":7}'))->toContain('Budi');
});

test('teks panjang tidak dipotong saat diekspor', function (): void {
    // Memotong isi cuma bikin data hilang. Teks panjang harus utuh.
    $long = str_repeat('Deskripsi bunga yang panjang. ', 40);

    expect(mb_strlen(ExportText::value($long)))->toBe(mb_strlen(trim($long)));
});

test('daftar dataset lengkap: semua exporter punya model dan tabel', function (): void {
    // Pusat Unduhan Data harus LENGKAP. Setiap dataset wajib punya exporter
    // yang menunjuk model nyata dengan tabel yang ada.
    $datasets = DataExportService::datasets();

    expect(count($datasets))->toBeGreaterThanOrEqual(50);

    foreach ($datasets as $key => $dataset) {
        expect($dataset)->toHaveKeys(['label', 'filename', 'exporter', 'group'])
            ->and($dataset['exporter'])->toBeString()
            ->and(class_exists($dataset['exporter']))->toBeTrue("exporter {$dataset['exporter']} tidak ada");

        $model = $dataset['exporter']::getModel();

        expect(class_exists($model))->toBeTrue("model {$model} tidak ada")
            ->and(Schema::hasTable((new $model)->getTable()))->toBeTrue("tabel {$model} tidak ada");
    }

    // Nama berkas harus unik -- dua dataset dengan nama berkas sama akan
    // saling menimpa di arsip.
    $filenames = array_column($datasets, 'filename');

    expect(array_unique($filenames))->toHaveCount(count($filenames));
});

test('XLSX yang dihasilkan bebas underscore dan slash di judul dan isi', function (): void {
    $path = DataExportService::buildWorkbook(['admins'], $this->admin);

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();

    $titles = $spreadsheet->getSheetNames();
    expect($titles)->not->toContain('_');
    expect($titles)->not->toContain('/');

    $found = false;

    for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
        for ($col = 1; $col <= 5; $col++) {
            $value = $sheet->getCell([$col, $row])->getValue();

            if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $value = $value->getPlainText();
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            expect($value)->not->toContain('_');
            expect($value)->not->toContain('/');

            $found = true;
        }
    }

    expect($found)->toBeTrue();
});

test('PDF bebas underscore dan slash, dan tabelnya rata kiri-kanan', function (): void {
    // `admins` sengaja dipilih: kolomnya banyak (12) dan isinya memuat
    // nilai mentah seperti "super_admin" dan URL avatar.
    $path = DataExportService::buildPdf(['admins'], $this->admin);

    $rows = pdfTextRows(File::get($path));

    expect($rows)->not->toBeEmpty();

    foreach ($rows as [$x, $y, $size, $text]) {
        expect($text)->not->toContain('_', "teks PDF masih punya underscore: {$text}");
        expect($text)->not->toContain('/', "teks PDF masih punya slash: {$text}");
    }

    // Tabel harus membentang sampai sisi kanan kertas (A4 landscape, margin
    // kiri 4 cm = 113,4 pt; lebarArea cetak 643,5 pt). Kolom terakhir
    // dimulai jauh di kanan kalau tabelnya benar-benar "rata kanan-kiri".
    $maxX = max(array_map(fn (array $row): float => $row[0], $rows));

    expect($maxX)->toBeGreaterThan(600, "tabel PDF tidak melebar ke sisi kanan (x terakhir {$maxX})");
});

test('lebar kolom PDF berjumlah persis 100 persen', function (): void {
    // Tanpa ini, tabel bisa melebar melewati tepi kanan kertas atau
    // menyisakan ruang kosong di sebelah kanan.
    $method = new ReflectionMethod(DataExportService::class, 'pdfColumnWidths');

    $widths = $method->invoke(null, ['ID', 'Nama Lengkap', 'Keterangan'], [
        [1, 'Budi Santoso', 'Deskripsi'],
        [2, 'Siti Aminah', 'Teks jauh lebih panjang sekali untuk membesize kolom ini'],
    ]);

    expect($widths)->toBeArray()
        ->and(count($widths))->toBe(3)
        ->and(array_sum($widths))->toBe(100.0);

    foreach ($widths as $width) {
        expect($width)->toBeGreaterThan(0);
    }
});

test('route download melayani pdf lewat GET tanpa 419', function (): void {
    $path = DataExportService::buildPdf(['categories'], $this->user);

    $response = $this->get(route('admin.data-exports.download', ['file' => basename($path)]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('route download menolak ekstensi selain zip/xlsx/pdf', function (): void {
    $this->get(route('admin.data-exports.download', ['file' => 'data-aplikasi-20240101.exe']))->assertNotFound();
    $this->get(route('admin.data-exports.download', ['file' => 'tidak-ada.pdf']))->assertNotFound();
});

test('aksi unduh tidak memindahkan browser keluar dari halaman', function (): void {
    // Regression: halaman ini sebelumnya memakai `$livewire->redirect()` ke URL
    // unduhan. Redirect = navigasi penuh, jadi browser sempat memuat ulang
    // dokumen, dan begitu token/sesi bergeser (login di tab lain) livewire.js
    // memunculkan dialog "This page has expired" tepat setelah berkas selesai
    // diunduh. Sekarang unduhan dipicu lewat iframe tersembunyi, jadi hanya
    // berkasnya yang turun dan halaman tetap seperti semula.
    //
    // Hanya `categories` yang dinyalakan supaya test ini tidak membangun 21
    // dataset sekaligus.
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    try {
        Livewire::test(DataExportPage::class)
            ->set('data', ['categories' => true])
            ->callAction('unduh_data', data: ['format' => 'xlsx'])
            ->assertNoRedirect()
            ->assertNotified();
    } finally {
        Filament::setCurrentPanel(null);
    }
});

test('route token csrf livewire mengembalikan token sesi terbaru', function (): void {
    // Dipakai skrip pemulihan 419: dialog bawaan dibatalkan, token baru
    // diambil dari sini, lalu request yang sama diulang. Tanpa endpoint ini,
    // satu-satunya pilihan lain adalah memuat ulang halaman dan kehilangan
    // isian form yang sudah diisi.
    $response = $this->get(route('livewire.csrf-token'));

    $response->assertOk();

    expect($response->json('token'))->toBeString()
        ->and($response->json('token'))->not->toBe('');
});
