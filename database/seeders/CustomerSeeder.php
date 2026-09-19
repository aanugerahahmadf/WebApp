<?php

namespace Database\Seeders;

use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Seeding Customer Users ---');

        $users = [
            [
                'email' => 'customer@demo.com',
                'full_name' => 'Andi Pratama',
                'first_name' => 'Andi',
                'last_name' => 'Pratama',
                'username' => 'andipratama',
                'password' => Hash::make('@Customer123'),
                'whatsapp' => '6281234567891',
                'identity_type' => 'ktp',
                'ktp_number' => '1234567890123457',
                'birth_place' => 'Jakarta',
                'birth_date' => '1995-05-15',
                'gender' => 'male',
                'religion' => 'Islam',
                'marital_status' => 'Menikah',
                'mother_name' => 'Siti Nurjanah',
                'occupation' => 'Karyawan',
                'income_range' => 'Rp 5 - 10 Juta',
                'source_of_funds' => 'Gaji',
                'country' => 'Indonesia',
                'email_verified_at' => now(),
                'avatar_url' => 'https://i.pravatar.cc/300?u=andipratama',
            ],
            [
                'email' => 'customer2@demo.com',
                'full_name' => 'Siti Rahmawati',
                'first_name' => 'Siti',
                'last_name' => 'Rahmawati',
                'username' => 'sitirahma',
                'password' => Hash::make('@Customer123'),
                'whatsapp' => '6281234567892',
                'identity_type' => 'ktp',
                'ktp_number' => '1234567890123458',
                'birth_place' => 'Bandung',
                'birth_date' => '1998-10-20',
                'gender' => 'female',
                'religion' => 'Islam',
                'marital_status' => 'Belum Menikah',
                'mother_name' => 'Dewi Sartika',
                'occupation' => 'Mahasiswa',
                'income_range' => '< Rp 1 Juta',
                'source_of_funds' => 'Orang Tua',
                'country' => 'Indonesia',
                'email_verified_at' => now(),
                'avatar_url' => 'https://i.pravatar.cc/300?u=sitirahma',
            ],
        ];

        foreach ($users as $data) {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                $user = User::create($data);
            } else {
                $user->update($data);
            }

            if (! $user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            $this->command->line("  <info>✓</info> Customer Created: {$user->email}");
        }

        $this->command->info('--- Customer Seeding Complete ---');
    }
}
