<?php

/*
 * =============================================================================
 * FILE INI MASIH DI NONAKTIFKAN -- kodenya TIDAK dihapus.
 * =============================================================================
 *
 * Pendaftaran email/password sekarang sudah HIDAK LAGI, tapi file ini tetap
 * dibungkus komentar, dan alasannya BUKAN "pendaftaran dimatikan" lagi:
 *
 *   - Panggilannya ada di `WelcomePanelProvider` (panel 'user' dilebur ke
 *     panel 'welcome' pada 2026-10-21), jadi `filament.welcome.auth.register`
 *     terdaftar dan `/signup` adalah halaman utuh. Lihat
 *     `tests/Feature/User/SignUpRestoredTest/SignUpRestoredTest.php`.
 *   - Isi file ini memanggil `handleRegistration()` secara langsung lewat
 *     Livewire, yaitu memanggil method halaman secara UNIT -- bukan alur yang
 *     dipakai pengguna. Kalau form-nya berubah, file ini bisa tetap hijau
 *     sambil tidak ada yang benar-benar tercakup.
 *   - Alur yang dipakai pengguna sudah punya test sendiri:
 *     `SignUpRestoredTest` (halaman, form, link, crumb, topbar),
 *     `SignUpFlowTest` (setelah OTP: security question lalu 2FA), dan
 *     `RenameSmokeTest` (nama class + route).
 *
 * Jadi file ini tidak dihidupkan: menghidupkannya hanya menambah test yang
 * mengulang test yang sudah ada, dengan cara yang lebih rapuh.
 *
 * CARA MENGHIDUPKAN (kalau memang perlu -- baca dulu alasannya di atas):
 *   (1) Hapus baris pembuka blok komentar yang ada tepat di bawah bagian
 *       ini -- baris yang isinya hanya simbol pembuka komentar blok --
 *       supaya isi blok kembali menjadi kode.
 *   (2) Cek nama field form di `app/Filament/User/Auth/SignUp/SignUp.php`
 *       masih sama dengan yang dipakai di blok komentar. Form pendaftaran
 *       sekarang juga memuat KYC (KTP/selfie/avatar) dan dirender di
 *       `/complete-profile` -- bukan di halaman daftar.
 *   (3) Jalankan file ini dan pastikan tidak ada assertion yang bentrok
 *       dengan `SignUpRestoredTest`.
 *
 * Tidak ada satu baris kode yang dihapus di bawah ini -- semuanya dibungkus
 * komentar, persis seperti cara file ini dimatikan.
 */
/*
 *
 * use App\Filament\User\Auth\SignUp\SignUp;
 * use App\Livewire\User\CompleteProfileComponent\CompleteProfileComponent;
 * use App\Models\User\User;
 * use Filament\Facades\Filament;
 * use Illuminate\Foundation\Testing\RefreshDatabase;
 * use Illuminate\Support\Facades\Hash;
 * use Livewire\Livewire;
 * use Spatie\Permission\Models\Role;
 *
 * uses(RefreshDatabase::class);
 *
 * beforeEach(function (): void {
 *     Role::firstOrCreate(['name' => 'customer']);
 *     Filament::setCurrentPanel(Filament::getPanel('user'));
 * });
 *
 * it('registers a new user through the panel form', function (): void {
 *     Livewire::test(SignUp::class)
 *         ->fillForm([
 *             'username' => 'john.doe',
 *             'email' => 'john.doe@example.com',
 *             'password' => 'StrongPass123!',
 *             'password_confirmation' => 'StrongPass123!',
 *             'first_name' => 'John',
 *             'mid_name' => 'Adam',
 *             'last_name' => 'Doe',
 *             'whatsapp_country_code' => '+62|ID',
 *             'whatsapp' => '8123456789',
 *             'birth_place_date' => 'Jakarta, 12/05/1990',
 *             'country' => 'Indonesia',
 *             'address' => 'Jl. Merdeka No. 1, Jakarta',
 *             'gender' => 'Pria',
 *             'occupation' => 'Karyawan',
 *             'agreement' => true,
 *             'remember' => true,
 *         ])
 *         ->call('register')
 *         ->assertHasNoFormErrors();
 *
 *     $this->assertDatabaseHas('users', [
 *         'email' => 'john.doe@example.com',
 *         'username' => 'john.doe',
 *         'first_name' => 'John',
 *         'mid_name' => 'Adam',
 *         'last_name' => 'Doe',
 *         'full_name' => 'John Adam Doe',
 *         'country' => 'Indonesia',
 *         'address' => 'Jl. Merdeka No. 1, Jakarta',
 *         'gender' => 'Pria',
 *         'occupation' => 'Karyawan',
 *         'liveness_completed' => false,
 *     ]);
 *
 *     $user = User::where('email', 'john.doe@example.com')->first();
 *
 *     expect($user)->not->toBeNull();
 *
 *     // `birth_place_date` dipecah jadi dua kolom: tempat dan tanggal.
 *     expect($user->birth_place)->toBe('Jakarta')
 *         ->and($user->birth_date->format('Y-m-d'))->toBe('1990-05-12')
 *         // Nomor disimpan dalam format E.164, sama dengan OTP dan profil --
 *         // kalau tidak, nomor yang sama tidak akan cocok antar layar.
 *         ->and($user->whatsapp)->toBe('+628123456789')
 *         ->and(Hash::check('StrongPass123!', $user->password))->toBeTrue()
 *         ->and($user->hasRole('customer'))->toBeTrue();
 * });
 *
 * it('rejects registration when agreement is not checked', function (): void {
 *     Livewire::test(SignUp::class)
 *         ->fillForm([
 *             'username' => 'jane.doe',
 *             'email' => 'jane.doe@example.com',
 *             'password' => 'StrongPass123!',
 *             'password_confirmation' => 'StrongPass123!',
 *             'first_name' => 'Jane',
 *             'last_name' => 'Doe',
 *             'whatsapp_country_code' => '+62|ID',
 *             'whatsapp' => '8123456789',
 *             'birth_place_date' => 'Jakarta, 12/05/1990',
 *             'country' => 'Indonesia',
 *             'address' => 'Jl. Merdeka No. 1, Jakarta',
 *             'gender' => 'Wanita',
 *             'occupation' => 'Karyawan',
 *             'agreement' => false,
 *             'remember' => true,
 *         ])
 *         ->call('register')
 *         ->assertHasFormErrors(['agreement']);
 *
 *     $this->assertDatabaseCount('users', 0);
 * });
 *
 * it('rejects registration when remember me is not checked', function (): void {
 *     // Gate kedua, dan sering terlewat: tanpa ini checkbox "Ingat Saya" hanya
 *     // formalitas, karena tidak ada yang membacanya.
 *     Livewire::test(SignUp::class)
 *         ->fillForm([
 *             'username' => 'rudi.doe',
 *             'email' => 'rudi.doe@example.com',
 *             'password' => 'StrongPass123!',
 *             'password_confirmation' => 'StrongPass123!',
 *             'first_name' => 'Rudi',
 *             'last_name' => 'Doe',
 *             'whatsapp_country_code' => '+62|ID',
 *             'whatsapp' => '8123456789',
 *             'birth_place_date' => 'Jakarta, 12/05/1990',
 *             'country' => 'Indonesia',
 *             'address' => 'Jl. Merdeka No. 1, Jakarta',
 *             'gender' => 'Pria',
 *             'occupation' => 'Karyawan',
 *             'agreement' => true,
 *             'remember' => false,
 *         ])
 *         ->call('register')
 *         ->assertHasFormErrors(['remember']);
 *
 *     $this->assertDatabaseCount('users', 0);
 * });
 *
 * it('saves the complete profile from the livewire component', function (): void {
 *     $user = User::factory()->create();
 *
 *     Livewire::actingAs($user)
 *         ->test(CompleteProfileComponent::class)
 *         ->fillForm([
 *             'first_name' => 'Jane',
 *             'mid_name' => 'Ann',
 *             'last_name' => 'Doe',
 *             'whatsapp_country_code' => '+62|ID',
 *             'whatsapp' => '8123456789',
 *             'birth_place_date' => 'Bandung, 20/08/1992',
 *             'country' => 'Indonesia',
 *             'address' => 'Jl. Braga No. 2, Bandung',
 *             'gender' => 'Wanita',
 *             'occupation' => 'Wiraswasta',
 *         ])
 *         ->call('save')
 *         ->assertRedirect(route('filament.welcome.pages.home'));
 *
 *     $user->refresh();
 *
 *     // Ini jalur yang benar-benar dipakai pengguna yang mendaftar lewat Google:
 *     // akun sudah ada, tinggal melengkapi profil.
 *     expect($user->full_name)->toBe('Jane Ann Doe')
 *         ->and($user->whatsapp)->toBe('+628123456789')
 *         ->and($user->birth_place)->toBe('Bandung')
 *         ->and($user->birth_date->format('Y-m-d'))->toBe('1992-08-20')
 *         // Melengkapi profil bukan berarti identitas terverifikasi.
 *         ->and($user->liveness_completed)->toBeFalse();
 * });
 */

