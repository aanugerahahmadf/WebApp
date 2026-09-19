<?php

namespace Database\Seeders;

use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravolt\Indonesia\Models\City as IndonesiaCity;
use Laravolt\Indonesia\Models\District as IndonesiaDistrict;
use Laravolt\Indonesia\Models\Province as IndonesiaProvince;
use Laravolt\Indonesia\Models\Village as IndonesiaVillage;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('--- Seeding Super Admin ---');

        $province = IndonesiaProvince::where('name', 'DKI JAKARTA')->first();
        $city = $province ? IndonesiaCity::where('province_code', $province->code)->where('name', 'JAKARTA SELATAN')->first() : null;
        $district = $city ? IndonesiaDistrict::where('city_code', $city->code)->first() : null;
        $village = $district ? IndonesiaVillage::where('district_code', $district->code)->first() : null;

        $user = User::where('email', 'devimakeup.wo@gmail.com')->first();
        if (! $user) {
            $user = User::where('username', 'superadmin')->first();
        }
        if ($user) {
            $user->update([
                'identity_type' => 'ktp',
                'full_name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'password' => Hash::make('@Superadmin123'),
                'email_verified_at' => now(),
                'avatar_url' => 'https://i.pravatar.cc/300?u=superadmin',
                'whatsapp' => '6281234567890',
                'ktp_number' => '1234567890123456',
                'birth_place' => 'Jakarta',
                'birth_date' => '1990-01-01',
                'gender' => 'male',
                'religion' => 'Islam',
                'marital_status' => 'Menikah',
                'mother_name' => 'Ibu Super Admin',
                'occupation' => 'Profesional',
                'income_range' => '> Rp 50 Juta',
                'source_of_funds' => 'Gaji',
                'country' => 'Indonesia',
                'province_id' => $province?->id,
                'city_id' => $city?->id,
                'district_id' => $district?->id,
                'village_id' => $village?->id,
                'province_name' => $province?->name ?? 'DKI Jakarta',
                'city_name' => $city?->name ?? 'Jakarta Selatan',
                'district_name' => $district?->name ?? 'Kebayoran Baru',
                'village_name' => $village?->name ?? 'Gandaria Utara',
                'postal_code' => '12140',
                'address' => 'Jl. M.H. Thamrin No. 1, Gandaria Utara',
            ]);
        } else {
            $user = User::create([
                'identity_type' => 'ktp',
                'full_name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'email' => 'devimakeup.wo@gmail.com',
                'password' => Hash::make('@Superadmin123'),
                'email_verified_at' => now(),
                'avatar_url' => 'https://i.pravatar.cc/300?u=superadmin',
                'whatsapp' => '6281234567890',
                'ktp_number' => '1234567890123456',
                'birth_place' => 'Jakarta',
                'birth_date' => '1990-01-01',
                'gender' => 'male',
                'religion' => 'Islam',
                'marital_status' => 'Menikah',
                'mother_name' => 'Ibu Super Admin',
                'occupation' => 'Profesional',
                'income_range' => '> Rp 50 Juta',
                'source_of_funds' => 'Gaji',
                'country' => 'Indonesia',
                'province_id' => $province?->id,
                'city_id' => $city?->id,
                'district_id' => $district?->id,
                'village_id' => $village?->id,
                'province_name' => $province?->name ?? 'DKI Jakarta',
                'city_name' => $city?->name ?? 'Jakarta Selatan',
                'district_name' => $district?->name ?? 'Kebayoran Baru',
                'village_name' => $village?->name ?? 'Gandaria Utara',
                'postal_code' => '12140',
                'address' => 'Jl. M.H. Thamrin No. 1, Gandaria Utara',
            ]);
        }

        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $this->command->line("  <info>✓</info> Super Admin Created: {$user->email}");
        $this->command->info('--- Super Admin Seeding Complete ---');
    }
}
