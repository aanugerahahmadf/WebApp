<?php

use App\Models\User\User;
use App\Services\DataExportService\DataExportService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    actingAs($this->user, 'web');

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

test('humanize menghapus _, -, dan / dari teks label', function (): void {
    $method = new ReflectionMethod(DataExportService::class, 'humanizeText');

    // Label: pemisah berubah jadi spasi, Title Case.
    expect($method->invoke(null, 'super_admin'))->toBe('Super Admin')
        ->and($method->invoke(null, 'paket-dekorasi'))->toBe('Paket Dekorasi')
        ->and($method->invoke(null, 'admin/super_admin'))->toBe('Admin Super Admin')
        ->and($method->invoke(null, 'KTP/Passport'))->toBe('Ktp Passport');

    // Bukan label: URL, email, desimal, tanggal, UUID dibiarkan utuh.
    expect($method->invoke(null, 'https://web.id/unduh'))->toBe('https://web.id/unduh')
        ->and($method->invoke(null, 'user@mail.com'))->toBe('user@mail.com')
        ->and($method->invoke(null, '10/20'))->toBe('10/20')
        ->and($method->invoke(null, '2026-10-02'))->toBe('02 Okt 2026');
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
