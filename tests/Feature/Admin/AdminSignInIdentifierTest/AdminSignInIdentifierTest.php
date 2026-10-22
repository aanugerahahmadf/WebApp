<?php

namespace Tests\Feature\Admin\AdminSignInIdentifierTest;

use App\Filament\Admin\Auth\SignIn\SignIn;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sign-In panel admin: field identitas menerima EMAIL **atau** USERNAME.
 *
 * Tabel `admins` punya dua kolom unik: `username` dan `email`. Dulu form
 * sign-in panel admin hanya punya SATU field berlabel "Email address" dengan
 * validasi email bawaan Filament -- jadi admin yang memakai username
 * ("superadmin") tidak bisa masuk: nomornya ditolak sebagai "bukan email"
 * sebelum kredensial dicek.
 *
 * Yang dijaga di sini:
 *   1. fieldnya berlabel "Email / Username" dan TIDAK punya validasi email,
 *   2. kredensial dipetakan ke kolom email atau username sesuai bentuknya,
 *   3. login dengan USERNAME berhasil (live, bukan cuma unit),
 *   4. login dengan EMAIL berhasil,
 *   5. akun nonaktif ditolak dengan pesan yang jelas,
 *   6. password salah ditolak dengan pesan yang menyebut keduanya.
 */
final class AdminSignInIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia-admin-2026';

    protected function setUp(): void
    {
        parent::setUp();

        seedRoles(adminRoles: ['super_admin']);
    }

    private function makeAdmin(array $attributes = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'username' => 'superadmin',
            'email' => 'superadmin@example.test',
            'password' => Hash::make(self::PASSWORD),
            'active_status' => true,
        ], $attributes));
    }

    /**
     * Kolom mana yang dipakai untuk kredensial `login`.
     *
     * @return array<string, mixed>
     */
    private function credentialsFor(string $login): array
    {
        $page = (new ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(SignIn::class, 'getCredentialsFromFormData');
        $method->setAccessible(true);

        return $method->invoke($page, ['login' => $login, 'password' => 'apa-saja']);
    }

    public function test_the_identifier_field_is_labelled_email_or_username(): void
    {
        $page = (new ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(SignIn::class, 'getEmailFormComponent');
        $method->setAccessible(true);

        $component = $method->invoke($page);

        $this->assertSame('login', $component->getName());
        $this->assertSame('Email / Username', $component->getLabel());
    }

    public function test_the_identifier_field_does_not_validate_the_email_format(): void
    {
        // Validasi email bawaan Filament akan menolak "superadmin" SEBELUM
        // kredensial dicek -- itu sebabnya admin berbasis username tidak
        // bisa masuk.
        $page = (new ReflectionClass(SignIn::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(SignIn::class, 'getEmailFormComponent');
        $method->setAccessible(true);

        $component = $method->invoke($page);

        $rules = $component->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertNotContains('email', $rules, 'Field identitas tidak boleh divalidasi sebagai email.');
        $this->assertNotContains('email', (array) ($rules['email'] ?? []));
    }

    public function test_an_email_identifier_maps_to_the_email_column(): void
    {
        $this->assertSame(
            ['email' => 'superadmin@example.test', 'password' => 'apa-saja'],
            $this->credentialsFor('superadmin@example.test'),
        );
    }

    public function test_a_username_identifier_maps_to_the_username_column(): void
    {
        $this->assertSame(
            ['username' => 'superadmin', 'password' => 'apa-saja'],
            $this->credentialsFor('superadmin'),
        );
    }

    public function test_login_with_a_username_succeeds(): void
    {
        $admin = $this->makeAdmin();

        Livewire::test(SignIn::class)
            ->fillForm([
                'login' => 'superadmin',
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(
            auth()->guard('admin')->check(),
            'Admin harus login ke guard admin setelah sign in dengan username.',
        );
        $this->assertSame($admin->getKey(), auth()->guard('admin')->user()?->getKey());
    }

    public function test_login_with_an_email_succeeds(): void
    {
        $this->makeAdmin();

        Livewire::test(SignIn::class)
            ->fillForm([
                'login' => 'superadmin@example.test',
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(auth()->guard('admin')->check());
    }

    public function test_a_deactivated_account_is_rejected_with_a_clear_message(): void
    {
        $this->makeAdmin(['active_status' => false]);

        Livewire::test(SignIn::class)
            ->fillForm([
                'login' => 'superadmin',
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertFalse(auth()->guard('admin')->check());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->makeAdmin();

        Livewire::test(SignIn::class)
            ->fillForm([
                'login' => 'superadmin',
                'password' => 'kata-sandi-salah',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertFalse(auth()->guard('admin')->check());
    }

    public function test_the_failure_message_mentions_both_identifiers(): void
    {
        $this->makeAdmin();

        Livewire::test(SignIn::class)
            ->fillForm([
                'login' => 'superadmin',
                'password' => 'kata-sandi-salah',
            ])
            ->call('authenticate')
            ->assertHasFormErrors([
                'login' => 'Email/Username atau kata sandi tidak cocok.',
            ]);
    }
}
