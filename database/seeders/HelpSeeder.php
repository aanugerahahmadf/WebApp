<?php

namespace Database\Seeders;

use App\Models\Help\Help;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;

class HelpSeeder extends Seeder
{
    use TranslatesContent;

    public function run(): void
    {
        $this->command->info('--- Seeding Help Center ---');

        $idFaqs = [
            [
                'question' => 'Bagaimana cara memesan paket dekorasi?',
                'answer' => 'Anda dapat memilih produk atau paket yang diinginkan melalui aplikasi, lalu melanjutkan ke proses pembayaran Down Payment (DP) untuk mengamankan tanggal acara.',
            ],
            [
                'question' => 'Apakah saya bisa menjadwal ulang (reschedule) tanggal acara?',
                'answer' => 'Penjadwalan ulang diperbolehkan selambat-lambatnya 30 hari sebelum acara, bergantung pada ketersediaan jadwal tim kami.',
            ],
            [
                'question' => 'Apakah DP bisa dikembalikan jika acara batal?',
                'answer' => 'Down Payment (DP) bersifat non-refundable karena penjadwalan tim eksklusif. Untuk situasi Force Majeure, kami menawarkan opsi penjadwalan ulang berdasarkan kesepakatan bersama.',
            ],
        ];

        $enFaqs = [
            [
                'question' => 'How do I order a decoration package?',
                'answer' => 'You can select the desired product or package through the app, then proceed to the Down Payment (DP) process to secure the event date.',
            ],
            [
                'question' => 'Can I reschedule the event date?',
                'answer' => 'Rescheduling is permitted no later than 30 days before the event, subject to our team availability.',
            ],
            [
                'question' => 'Is the DP refundable if the event is cancelled?',
                'answer' => 'The Down Payment (DP) is non-refundable due to exclusive team scheduling. For Force Majeure situations, we offer rescheduling options based on mutual agreement.',
            ],
        ];

        $faqsTranslations = $this->translateArrayToAllLocales($idFaqs, $enFaqs);

        Help::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Pusat Bantuan',
                'title_translations' => $this->translateToAllLocales('Pusat Bantuan', 'Help Center'),
                'subtitle' => 'Tim kami siap membantu kebutuhan dekorasi pernikahan Anda.',
                'subtitle_translations' => $this->translateToAllLocales('Tim kami siap membantu kebutuhan dekorasi pernikahan Anda.', 'Our team is ready to assist with your wedding decoration needs.'),
                'faqs' => $idFaqs,
                'faqs_translations' => $faqsTranslations,
                'contact_options' => [
                    [
                        'label' => 'WhatsApp Support',
                        'subLabel' => '+62 812-3456-7890',
                        'url' => 'https://wa.me/6281234567890',
                        'icon' => 'whatsapp',
                    ],
                    [
                        'label' => 'Email Support',
                        'subLabel' => 'support@weddingapp.com',
                        'url' => 'mailto:support@weddingapp.com',
                        'icon' => 'mail',
                    ],
                ],
            ]
        );

        $this->command->line('  <info>✓</info> Help Center seeded');
        $this->command->info('--- Help Center Seeding Complete ---');
    }
}
