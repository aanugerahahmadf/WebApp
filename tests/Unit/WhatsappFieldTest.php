<?php

/**
 * Guard: field WhatsApp harus seragam di keempat tempat, dan label opsi
 * negara harus memuat bendera emoji.
 *
 * Catatan gaya: expectation milik Pest (toHaveKey, toContain) bersifat
 * variadic, jadi argumen kedua dibaca sebagai nilai yang diharapkan -- bukan
 * pesan. Untuk pesan kustom pakai expect(...)->toBeTrue("...") atau
 * assertArrayHasKey.
 */

use App\Support\Phone\CountryCallingCodeOptions;
use App\Support\Phone\WhatsappField;

it('opsi negara memuat bendera emoji, bukan hanya kode ISO', function (): void {
    $options = CountryCallingCodeOptions::all();

    expect($options)->not->toBeEmpty();
    expect(array_key_exists('+62|ID', $options))->toBeTrue('Indonesia tidak ada di daftar');

    expect($options['+62|ID'])
        ->toContain('country-flag')
        ->toContain(CountryCallingCodeOptions::flag('ID'))
        ->toContain('Indonesia');

    foreach (['+1|US' => '🇺🇸', '+81|JP' => '🇯🇵', '+44|GB' => '🇬🇧', '+65|SG' => '🇸🇬', '+60|MY' => '🇲🇾'] as $key => $flag) {
        expect(array_key_exists($key, $options))->toBeTrue("kode negara {$key} tidak ada di daftar");
        expect($options[$key])->toContain($flag);
    }
});

it('flag() mengembalikan string kosong untuk kode yang bukan dua huruf', function (): void {
    expect(CountryCallingCodeOptions::flag('001'))->toBe('');
    expect(CountryCallingCodeOptions::flag('XYZ'))->toBe('');
    expect(CountryCallingCodeOptions::flag(''))->toBe('');
});

it('nama negara di-escape dan kunci opsi selalu "+dial|ISO"', function (): void {
    foreach (CountryCallingCodeOptions::all() as $key => $label) {
        expect($label)->not->toContain('<script');
        expect((bool) preg_match('/^\+\d+\|[A-Z]{2}$/', $key))->toBeTrue("kunci opsi tak valid: {$key}");
    }
});

it('normalize() menyamakan nomor dengan format berbeda', function (): void {
    // Semua ini harus jadi nomor yang sama supaya OTP tidak dianggap
    // "nomor berubah" -- termasuk format '+62 812...' yang dulu ditulis
    // PersonalInfoComponentSuperAdmin.
    $canonical = WhatsappField::normalize('+6281234567890');

    expect($canonical)->toBe('+6281234567890');
    expect(WhatsappField::normalize('+62 81234567890'))->toBe($canonical);
    expect(WhatsappField::normalize('+62 812 3456 7890'))->toBe($canonical);
    expect(WhatsappField::normalize('081234567890'))->toBe($canonical);
    expect(WhatsappField::normalize('81234567890'))->toBe($canonical);
    expect(WhatsappField::normalize('006281234567890'))->toBe($canonical);
    expect(WhatsappField::normalize(''))->toBe('');
    expect(WhatsappField::normalize(null))->toBe('');
});

it('state() memecah nomor E.164 jadi pilihan negara + nomor lokal', function (): void {
    expect(WhatsappField::state('+6281234567890'))
        ->toBe(['whatsapp_country_code' => '+62|ID', 'whatsapp' => '81234567890']);

    // Nomor kosong harus jatuh ke default, bukan melempar.
    expect(WhatsappField::state(null))
        ->toBe(['whatsapp_country_code' => CountryCallingCodeOptions::defaultSelection(), 'whatsapp' => '']);
});

it('keempat tempat memakai builder WhatsApp yang sama', function (): void {
    // SuperAdmin pernah punya prefix '+62' statis dan tidak punya Select
    // negara, lalu menyimpan nomor sebagai '+62 812...' (pakai spasi) sehingga
    // tidak cocok dengan OTP. Sekarang semuanya memanggil builder yang sama,
    // jadi tidak bisa berbeda lagi.
    foreach ([
        'app/Livewire/User/PersonalInfoComponent/PersonalInfoComponent.php',
        'app/Livewire/Admin/PersonalInfoComponentSuperAdmin/PersonalInfoComponentSuperAdmin.php',
        'app/Livewire/User/CompleteProfileComponent/CompleteProfileComponent.php',
        'app/Filament/User/Auth/SignUp/SignUp.php',
    ] as $path) {
        $source = (string) file_get_contents(base_path($path));

        expect(str_contains($source, 'WhatsappField::group'))
            ->toBeTrue("{$path} tidak memakai WhatsappField::group");

        expect(str_contains($source, "prefix('+62')"))
            ->toBeFalse("{$path} masih memakai prefix '+62' statis");
    }
});

it('kedua komponen profil punya alur OTP yang sama', function (): void {
    // Yang paling sering selisih adalah urutan pemanggilan helper OTP.
    foreach ([
        'app/Livewire/User/PersonalInfoComponent/PersonalInfoComponent.php',
        'app/Livewire/Admin/PersonalInfoComponentSuperAdmin/PersonalInfoComponentSuperAdmin.php',
    ] as $path) {
        $source = (string) file_get_contents(base_path($path));

        foreach ([
            'WhatsappField::e164(',
            'WhatsappField::sendOtp(',
            'WhatsappField::normalize(',
            'WhatsappField::isOtpValid(',
            'WhatsappField::state(',
            "TextInput::make('otp_code')",
        ] as $needle) {
            expect(str_contains($source, $needle))
                ->toBeTrue("{$path} tidak punya alur OTP bersama: {$needle}");
        }
    }
});