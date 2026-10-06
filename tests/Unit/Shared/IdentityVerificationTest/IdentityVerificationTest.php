<?php

/*
 * `App\Support\IdentityVerification\IdentityVerification` -- satu-satunya tempat
 * yang boleh memberi status "terverifikasi" pada sebuah akun.
 *
 * Kenapa kelas ini butuh test sendiri, terpisah dari test halaman yang memakainya:
 * aturannya adalah invariant keamanan (satu-satunya tempatnya status verifikasi
 * ditulis), sementara test halaman hanya tidak sengaja menyentuhnya lewat form.
 * Kalau aturan ini berubah diam-diam, test halaman tetap hijau.
 *
 * Yang dikunci:
 *   1. `markVerified()` hanya mengisi `identity_verified_at` kalau AI benar-benar
 *      bilang Success DAN verified. Success saja tidak cukup.
 *   2. `liveness_completed` hanya terisi kalau AI melaporkan `success`. Liveness
 *      yang berhasil dan identitas yang sah adalah dua hal berbeda: liveness
 *      berarti pemeriksaan wajah berjalan dan tidak menemukan deepfake,
 *      identitas berarti wajahnya cocok dengan dokumen. Keduanya tidak boleh
 *      naik bersamaan hanya karena satu flag.
 *   3. `markUnverified()` membersihkan KETIGA kolom sekaligus. Kalau hanya
 *      `identity_verified_at` yang dibersihkan, `liveness_completed` dan
 *      `face_verified_at` yang tertinggal akan membuat UI menampilkan
 *      "terverifikasi" untuk dokumen yang sudah diganti.
 *   4. Penulisan memakai `forceFill()`, bukan `fill()`. Kolom ini tidak ada di
 *      `$fillable`, jadi tanpa `forceFill()` tidak ada satu pun yang tersimpan --
 *      dan test ini akan tetap hijau kalau hasilnya tidak dicek dengan benar.
 */

use App\Models\User\User;
use App\Support\IdentityVerification\IdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->verification = new IdentityVerification();

    $this->user = User::factory()->create([
        'identity_verified_at' => null,
        'liveness_completed' => false,
        'face_verified_at' => null,
    ]);
});

test('a verified ai result fills the verification timestamp', function (): void {
    $this->verification->markVerified($this->user, [
        'success' => true,
        'verified' => true,
    ]);

    $this->user->refresh();

    expect($this->user->identity_verified_at)->not->toBeNull()
        ->and($this->user->liveness_completed)->toBeTrue();
});

test('success without verified marks liveness but not identity', function (): void {
    // Percobaan wajah berjalan dan liveness lolos, tapi similarity di bawah
    // threshold. Ini harus mengisi liveness_completed SAJA: mengisi
    // identity_verified_at di sini akan menyatakan identitasnya sah padahal
    // wajahnya tidak cocok dengan dokumen.
    $this->verification->markVerified($this->user, [
        'success' => true,
        'verified' => false,
    ]);

    $this->user->refresh();

    expect($this->user->liveness_completed)->toBeTrue()
        ->and($this->user->identity_verified_at)->toBeNull();
});

test('verified without success marks nothing at all', function (): void {
    // Urutan field dibalik: `verified` true tapi `success` false. Kode lama yang
    // hanya mengecek `verified` akan salah menandai akun sebagai terverifikasi
    // dari respons AI yang sebenarnya gagal.
    $this->verification->markVerified($this->user, [
        'success' => false,
        'verified' => true,
    ]);

    $this->user->refresh();

    expect($this->user->identity_verified_at)->toBeNull()
        ->and($this->user->liveness_completed)->toBeFalse();
});

test('an empty ai result marks nothing', function (): void {
    // Hasil null / exception dari FaceService hanya_exception yang bisa lewat ke
    // sini. Array kosong tidak boleh diartikan sebagai "terverifikasi".
    $this->verification->markVerified($this->user, []);

    $this->user->refresh();

    expect($this->user->identity_verified_at)->toBeNull()
        ->and($this->user->liveness_completed)->toBeFalse();
});

test('marking unverified clears all three verification columns', function (): void {
    $this->verification->markVerified($this->user, [
        'success' => true,
        'verified' => true,
    ]);

    $this->user->forceFill([
        'face_verified_at' => now(),
    ])->save();

    $this->verification->markUnverified($this->user);

    $this->user->refresh();

    expect($this->user->identity_verified_at)->toBeNull()
        ->and($this->user->liveness_completed)->toBeFalse()
        ->and($this->user->face_verified_at)->toBeNull();
});

test('marking unverified on a never verified account changes nothing', function (): void {
    // Idempoten: halaman memanggilnya dari beberapa tempat, dan memanggil
    // reset pada akun yang belum pernah diverifikasi tidak boleh membuat
    // updated_at bergerak atau melempar apa pun.
    $updatedAtBefore = $this->user->fresh()->updated_at;

    $this->verification->markUnverified($this->user);

    $after = $this->user->fresh();

    expect($after->identity_verified_at)->toBeNull()
        ->and($after->liveness_completed)->toBeFalse()
        ->and($after->updated_at->equalTo($updatedAtBefore))->toBeTrue();
});

test('verification columns are not mass assignable so the writes are forced', function (): void {
    // Inilah alasan `forceFill()` dipakai. Kalau suatu saat kolom ini ikut masuk
    // $fillable, test ini akan gagal -- dan itu memang informasi yang berguna:
    // jalur form akan bisa menimpa status verifikasi tanpa lewat AI.
    $fillable = (new User())->getFillable();

    expect($fillable)
        ->not->toContain('identity_verified_at')
        ->not->toContain('liveness_completed')
        ->not->toContain('face_verified_at');
});