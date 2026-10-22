<?php

namespace Database\Factories;

use App\Models\Help\Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Help>
 */
class HelpFactory extends Factory
{
    protected $model = Help::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Pusat Bantuan & FAQ Wedding Decoration',
            'subtitle' => 'Tim kami siap membantu mewujudkan pernikahan impian Anda',
            'faqs' => [
                [
                    'question' => 'Bagaimana cara memesan dekorasi pernikahan?',
                    'answer' => 'Pilih paket dekorasi atau produk bunga yang diinginkan di katalog kami, lalu klik "Pesan Sekarang". Isi formulir tanggal acara, venue, dan preferensi warna. Tim kami akan menghubungi Anda dalam 24 jam untuk konsultasi gratis.',
                ],
                [
                    'question' => 'Berapa lama waktu yang dibutuhkan untuk setup dekorasi?',
                    'answer' => 'Waktu setup bergantung pada paket: Bronze (2-3 jam), Silver (3-4 jam), Gold (4-6 jam), Platinum (6-8 jam), dan Diamond (8-12 jam). Tim kami biasanya mulai setup di pagi hari agar siap sebelum acara dimulai.',
                ],
                [
                    'question' => 'Apakah bisa request custom desain di luar paket yang tersedia?',
                    'answer' => 'Tentu! Kami menerima kustomisasi desain pelaminan, backdrop, buket, dan centerpiece. Konsultasikan moodboard atau inspirasi Anda saat booking. Biaya tambahan akan dihitung berdasarkan kompleksitas desain dan jenis bunga yang diminta.',
                ],
                [
                    'question' => 'Bagaimana kebijakan refund dan pembatalan pesanan?',
                    'answer' => 'Pembatalan 30+ hari sebelum acara: refund 90%. Pembatalan 14-29 hari: refund 50%. Pembatalan kurang dari 14 hari: tidak ada refund karena bunga sudah dipesan ke supplier. Pengajuan refund melalui menu "Pesanan Saya" > "Ajukan Refund".',
                ],
                [
                    'question' => 'Apakah bunga yang digunakan bunga segar asli?',
                    'answer' => 'Ya, kami menggunakan 100% bunga segar pilihan dari supplier terpercaya di Bandung dan impor dari Belanda. Untuk souvenir dan hiasan permanen, kami juga menyediakan opsi bunga kering (dried flower) berkualitas premium.',
                ],
                [
                    'question' => 'Apa saja metode pembayaran yang diterima?',
                    'answer' => 'Kami menerima transfer bank (BCA, BNI, Mandiri, BRI), QRIS, kartu kredit/debit, dan sistem cicilan 0% melalui mitra pembayaran. Pembayaran DP minimum 30% untuk mengamankan tanggal booking Anda.',
                ],
                [
                    'question' => 'Apakah tersedia layanan di luar kota?',
                    'answer' => 'Ya! Kami melayani area Jabodetabek, Bandung, Yogyakarta, Surabaya, Bali, dan kota-kota besar lainnya. Biaya transportasi dan akomodasi tim akan dihitung terpisah berdasarkan lokasi venue.',
                ],
                [
                    'question' => 'Bagaimana cara mendapatkan voucher diskon?',
                    'answer' => 'Voucher diskon tersedia melalui: (1) Promo bulanan di halaman utama, (2) Referral program (ajak teman mendapat diskon), (3) Newsletter subscription, dan (4) Event kolaborasi pameran pernikahan. Gunakan kode voucher saat checkout.',
                ],
            ],
            'contact_options' => [
                [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp Konsultasi',
                    'value' => '6281234567890',
                ],
                [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp Customer Service',
                    'value' => '6289876543210',
                ],
                [
                    'type' => 'email',
                    'label' => 'Email Support',
                    'value' => 'support@weddingflowersdecoration.id',
                ],
                [
                    'type' => 'email',
                    'label' => 'Email Kerjasama Bisnis',
                    'value' => 'partnership@weddingflowersdecoration.id',
                ],
                [
                    'type' => 'phone',
                    'label' => 'Telepon Kantor (Senin-Jumat)',
                    'value' => '021-12345678',
                ],
                [
                    'type' => 'instagram',
                    'label' => 'Instagram',
                    'value' => '@weddingflowers.id',
                ],
                [
                    'type' => 'tiktok',
                    'label' => 'TikTok',
                    'value' => '@weddingflowers.id',
                ],
            ],
        ];
    }

    /**
     * Versi minimal (sedikit FAQ).
     */
    public function minimal(): static
    {
        return $this->state(fn () => [
            'faqs' => [
                [
                    'question' => 'Bagaimana cara memesan?',
                    'answer' => 'Pilih paket, isi formulir pemesanan, dan lakukan pembayaran.',
                ],
            ],
            'contact_options' => [
                [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp',
                    'value' => '6281234567890',
                ],
            ],
        ]);
    }

    /**
     * Versi dengan subtitle English.
     */
    public function english(): static
    {
        return $this->state(fn () => [
            'title' => 'Help Center & FAQ',
            'subtitle' => 'Our team is ready to make your dream wedding come true',
        ]);
    }
}
