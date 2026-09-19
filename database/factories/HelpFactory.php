<?php

namespace Database\Factories;

use App\Models\Help\Help;
use Illuminate\Database\Eloquent\Factories\Factory;

class HelpFactory extends Factory
{
    protected $model = Help::class;

    public function definition(): array
    {
        return [
            'title' => 'Help Center',
            'subtitle' => 'Our team is ready to assist you',
            'faqs' => [
                [
                    'question' => 'Bagaimana cara memesan dekorasi?',
                    'answer' => 'Anda dapat memesan dekorasi melalui website atau aplikasi mobile kami. Pilih paket yang diinginkan, lalu ikuti langkah pembayaran.',
                ],
                [
                    'question' => 'Apakah bisa melakukan refund?',
                    'answer' => 'Refund dapat diproses sesuai dengan kebijakan yang berlaku. Silakan hubungi tim support kami untuk informasi lebih lanjut.',
                ],
                [
                    'question' => 'Berapa lama proses dekorasi?',
                    'answer' => 'Proses dekorasi biasanya memakan waktu 3-6 jam tergantung kompleksitas dekorasi yang dipilih.',
                ],
            ],
            'contact_options' => [
                [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp',
                    'value' => '6281234567890',
                ],
                [
                    'type' => 'email',
                    'label' => 'Email',
                    'value' => 'support@weddingorganizer.com',
                ],
                [
                    'type' => 'phone',
                    'label' => 'Telepon',
                    'value' => '021-12345678',
                ],
            ],
        ];
    }
}
