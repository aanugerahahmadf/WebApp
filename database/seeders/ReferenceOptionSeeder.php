<?php

namespace Database\Seeders;

use App\Models\ReferenceOption\ReferenceOption;
use Illuminate\Database\Seeder;

class ReferenceOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            'gender' => [
                ['key' => 'male',   'label' => ['en' => 'Male',    'id' => 'Laki-laki']],
                ['key' => 'female', 'label' => ['en' => 'Female',  'id' => 'Perempuan']],
            ],
            'religion' => [
                ['key' => 'islam',      'label' => ['en' => 'Islam',     'id' => 'Islam']],
                ['key' => 'christian',  'label' => ['en' => 'Christian', 'id' => 'Kristen']],
                ['key' => 'catholic',   'label' => ['en' => 'Catholic',  'id' => 'Katolik']],
                ['key' => 'hindu',      'label' => ['en' => 'Hindu',     'id' => 'Hindu']],
                ['key' => 'buddha',     'label' => ['en' => 'Buddha',    'id' => 'Buddha']],
                ['key' => 'confucian',  'label' => ['en' => 'Confucian', 'id' => 'Konghucu']],
            ],
            'marital_status' => [
                ['key' => 'single',   'label' => ['en' => 'Single',        'id' => 'Belum Menikah']],
                ['key' => 'married',  'label' => ['en' => 'Married',       'id' => 'Menikah']],
                ['key' => 'divorced', 'label' => ['en' => 'Divorced',      'id' => 'Cerai']],
            ],
            'occupation' => [
                ['key' => 'employee',      'label' => ['en' => 'Employee',           'id' => 'Karyawan']],
                ['key' => 'entrepreneur',  'label' => ['en' => 'Entrepreneur',       'id' => 'Wiraswasta']],
                ['key' => 'student',       'label' => ['en' => 'Student',            'id' => 'Pelajar/Mahasiswa']],
                ['key' => 'housewife',     'label' => ['en' => 'Housewife',          'id' => 'Ibu Rumah Tangga']],
                ['key' => 'professional',  'label' => ['en' => 'Professional',       'id' => 'Profesional']],
                ['key' => 'other',         'label' => ['en' => 'Other',              'id' => 'Lainnya']],
            ],
            'income_range' => [
                ['key' => 'lessThan1M',    'label' => ['en' => '< Rp 1 Million',     'id' => '< Rp 1 Juta']],
                ['key' => 'range1to5M',    'label' => ['en' => 'Rp 1-5 Million',     'id' => 'Rp 1-5 Juta']],
                ['key' => 'range5to10M',   'label' => ['en' => 'Rp 5-10 Million',    'id' => 'Rp 5-10 Juta']],
                ['key' => 'range10to50M',  'label' => ['en' => 'Rp 10-50 Million',   'id' => 'Rp 10-50 Juta']],
                ['key' => 'moreThan50M',   'label' => ['en' => '> Rp 50 Million',    'id' => '> Rp 50 Juta']],
            ],
            'source_of_funds' => [
                ['key' => 'salary',    'label' => ['en' => 'Salary',           'id' => 'Gaji']],
                ['key' => 'business',  'label' => ['en' => 'Business',         'id' => 'Bisnis/Usaha']],
                ['key' => 'investment','label' => ['en' => 'Investment',       'id' => 'Investasi']],
                ['key' => 'gift',      'label' => ['en' => 'Gift/Inheritance', 'id' => 'Hadiah/Warisan']],
                ['key' => 'other',     'label' => ['en' => 'Other',            'id' => 'Lainnya']],
            ],
        ];

        foreach ($options as $type => $items) {
            foreach ($items as $i => $item) {
                ReferenceOption::updateOrCreate(
                    ['type' => $type, 'key' => $item['key']],
                    [
                        'label' => $item['label'],
                        'sort_order' => $i,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
