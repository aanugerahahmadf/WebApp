<?php

namespace Database\Seeders;

use App\Models\LegalPage\LegalPage;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\TermsOfService\TermsOfService;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Illuminate\Database\Seeder;

class LegalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Seeding Terms & Privacy ---');

        $idContent = [
            ['heading' => 'PENDAHULUAN', 'body' => 'Selamat datang di platform Dekorasi Bunga Pernikahan. Sebelum menggunakan Situs ini atau membuat Akun, harap baca Syarat Layanan berikut dengan cermat untuk memahami hak dan kewajiban hukum Anda sehubungan dengan manajemen dekorasi kami.', 'is_italic' => false],
            ['heading' => 'AKUN DAN KEAMANAN', 'body' => 'Dekorasi Bunga Pernikahan berhak menolak akses ke Situs atau Layanan demi melindungi integritas jadwal layanan kami. Anda bertanggung jawab menjaga kerahasiaan kata sandi dan aktivitas akun. Setiap tindakan dalam akun dianggap sebagai persetujuan Anda.', 'is_italic' => true],
            ['heading' => 'LAYANAN DAN TRANSAKSI', 'body' => 'Pemesanan product atau paket dianggap permanen setelah validasi Down Payment. Dashboard berfungsi sebagai bukti digital transaksional yang sah. Amandemen rincian pesanan hanya diizinkan melalui konfirmasi sistem selambat-lambatnya 30 hari sebelum hari acara.', 'is_italic' => false],
            ['heading' => 'PEMBATALAN & REFUND', 'body' => 'DP bersifat non-refundable karena penjadwalan tim eksklusif. Untuk Force Majeure (Bencana alam/pandemi), opsi penjadwalan ulang akan ditawarkan berdasarkan ketersediaan kalender internal kami dengan menjunjung tinggi asas kekeluargaan.', 'is_italic' => true],
            ['heading' => 'HAK CIPTA & PORTOFOLIO', 'body' => 'Dokumentasi dekorasi bunga adalah hak intelektual Dekorasi Bunga Pernikahan dan dapat digunakan sebagai portofolio resmi. Penggunaan aset digital kami secara komersial tanpa izin tertulis dilarang keras secara hukum.', 'is_italic' => false],
        ];

        $enContent = [
            ['heading' => 'INTRODUCTION', 'body' => 'Welcome to the Wedding Flower Decorations platform. Before using this Site or creating an Account, please read the following Terms of Service carefully to understand your legal rights and obligations regarding our decoration management.', 'is_italic' => false],
            ['heading' => 'ACCOUNT AND SECURITY', 'body' => 'Wedding Flower Decorations reserves the right to deny access to the Site or Services to protect the integrity of our service schedule. You are responsible for maintaining the confidentiality of your password and account activities. Any action within the account is deemed as your approval.', 'is_italic' => true],
            ['heading' => 'SERVICES AND TRANSACTIONS', 'body' => 'Product or package orders are considered permanent after Down Payment validation. The Dashboard serves as valid transactional digital evidence. Order detail amendments are only permitted through system confirmation no later than 30 days before the event day.', 'is_italic' => false],
            ['heading' => 'CANCELLATION & REFUND', 'body' => 'DP is non-refundable due to exclusive team scheduling. For Force Majeure (natural disaster/pandemic), rescheduling options will be offered based on our internal calendar availability with the principle of family welfare.', 'is_italic' => true],
            ['heading' => 'COPYRIGHT & PORTFOLIO', 'body' => 'Flower decoration documentation is the intellectual property of Wedding Flower Decorations and may be used as an official portfolio. Commercial use of our digital assets without written permission is strictly prohibited by law.', 'is_italic' => false],
        ];

        TermsOfService::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Ketentuan Layanan',
                'title_translations' => ['id' => 'Ketentuan Layanan', 'en' => 'Terms of Service'],
                'content' => $idContent,
                'content_translations' => ['id' => $idContent, 'en' => $enContent],
            ]
        );

        $this->command->line('  <info>✓</info> Terms & Conditions seeded');

        $idPrivacy = [
            ['heading' => 'KOMITMEN PRIVASI', 'body' => 'Dekorasi Bunga Pernikahan menangani tanggung jawab perlindungan data pribadi sesuai dengan UU Pelindungan Data Pribadi (UU PDP) dengan sangat serius. Kami berkomitmen penuh untuk melindungi kerahasiaan seluruh data dekorasi Anda.', 'is_italic' => false],
            ['heading' => 'PENGUMPULAN DATA', 'body' => 'Kami mengumpulkan data pribadi riil seperti nama lengkap, alamat email, lokasi acara, dan riwayat transaksi. Data otentikasi cepat melalui Google Login hanya digunakan untuk pembuatan identitas digital unik pada portal dekorasi kami.', 'is_italic' => true],
            ['heading' => 'PENGGUNAAN INFORMASI', 'body' => 'Kami menggunakan informasi Anda semata-mata untuk memproses pesanan dekorasi bunga, koordinasi internal, notifikasi jadwal, dan audit perlindungan hak hukum. Seluruh data koordinasi internal tetap berada di bawah pengawasan audit internal kami.', 'is_italic' => false],
            ['heading' => 'KEAMANAN SISTEM', 'body' => 'Platform kami menggunakan enkripsi SSL tingkat tinggi untuk seluruh transmisi data. Keamanan sesi login bersifat temporer guna menjamin perlindungan privasi real-time saat Anda mengakses dashboard Dekorasi Bunga Pernikahan.', 'is_italic' => false],
        ];

        $enPrivacy = [
            ['heading' => 'PRIVACY COMMITMENT', 'body' => 'Wedding Flower Decorations handles the responsibility of personal data protection in accordance with the Personal Data Protection Law (UU PDP) very seriously. We are fully committed to protecting the confidentiality of all your decoration data.', 'is_italic' => false],
            ['heading' => 'DATA COLLECTION', 'body' => 'We collect real personal data such as full name, email address, event location, and transaction history. Quick authentication data via Google Login is only used for creating a unique digital identity on our decoration portal.', 'is_italic' => true],
            ['heading' => 'USE OF INFORMATION', 'body' => 'We use your information solely for processing flower decoration orders, internal coordination, schedule notifications, and legal protection audits. All internal coordination data remains under our internal audit supervision.', 'is_italic' => false],
            ['heading' => 'SYSTEM SECURITY', 'body' => 'Our platform uses high-level SSL encryption for all data transmission. Login session security is temporary to ensure real-time privacy protection when you access the Wedding Flower Decorations dashboard.', 'is_italic' => false],
        ];

        PrivacyPolicy::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Kebijakan Privasi',
                'title_translations' => ['id' => 'Kebijakan Privasi', 'en' => 'Privacy Policy'],
                'content' => $idPrivacy,
                'content_translations' => ['id' => $idPrivacy, 'en' => $enPrivacy],
            ]
        );
        $this->command->line('  <info>✓</info> Privacy Policy seeded');

        $idPolicy = [
            ['heading' => 'KEBIJAKAN DEKORASI', 'body' => 'Wedding Flowers Decorasi berkomitmen untuk menyediakan layanan dekorasi bunga pernikahan berkualitas tinggi. Seluruh desain dan tata rias dekorasi dilakukan oleh tim profesional kami dengan pengalaman bertahun-tahun di industri pernikahan.', 'is_italic' => false],
            ['heading' => 'PROSES PEMESANAN', 'body' => 'Pemesanan dekorasi dilakukan melalui platform resmi kami. Setelah pemesanan dikonfirmasi, tim kami akan menghubungi Anda untuk konsultasi desain dan detail teknis dekorasi sesuai dengan konsep pernikahan yang diinginkan.', 'is_italic' => true],
            ['heading' => 'KUALITAS LAYANAN', 'body' => 'Kami menjamin penggunaan bahan bunga segar dan material dekorasi berkualitas terbaik. Setiap dekorasi akan dipasang dan dirawat oleh tim profesional kami selama acara berlangsung.', 'is_italic' => false],
            ['heading' => 'TANGGUNG JAWAB', 'body' => 'Wedding Flowers Decorasi bertanggung jawab penuh atas kualitas dan ketepatan waktu pemasangan dekorasi. Klien wajib memberikan akses lokasi yang memadai kepada tim kami pada hari pelaksanaan.', 'is_italic' => true],
            ['heading' => 'KEBIJAKAN PEMBATALAN', 'body' => 'Pembatalan sepihak oleh klien dikenakan biaya sesuai ketentuan yang tercantum dalam perjanjian kerja sama. Pengembalian dana hanya berlaku untuk pembatalan yang dilakukan minimal 30 hari sebelum hari acara.', 'is_italic' => false],
        ];

        $enPolicy = [
            ['heading' => 'DECORATION POLICY', 'body' => 'Wedding Flowers Decorasi is committed to providing high-quality wedding flower decoration services. All decoration designs and styling are carried out by our professional team with years of experience in the wedding industry.', 'is_italic' => false],
            ['heading' => 'ORDERING PROCESS', 'body' => 'Decoration orders are made through our official platform. Once the order is confirmed, our team will contact you for design consultation and technical decoration details according to your desired wedding concept.', 'is_italic' => true],
            ['heading' => 'SERVICE QUALITY', 'body' => 'We guarantee the use of fresh flower materials and best quality decoration materials. Each decoration will be installed and maintained by our professional team throughout the event.', 'is_italic' => false],
            ['heading' => 'RESPONSIBILITY', 'body' => 'Wedding Flowers Decorasi is fully responsible for the quality and timeliness of decoration installation. Clients must provide adequate location access to our team on the execution day.', 'is_italic' => true],
            ['heading' => 'CANCELLATION POLICY', 'body' => 'Unilateral cancellation by the client is subject to fees according to the provisions stated in the cooperation agreement. Refunds only apply to cancellations made at least 30 days before the event day.', 'is_italic' => false],
        ];

        WeddingDecorationPolicy::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Kebijakan Aplikasi',
                'title_translations' => ['id' => 'Kebijakan Aplikasi', 'en' => 'Application Policy'],
                'content' => $idPolicy,
                'content_translations' => ['id' => $idPolicy, 'en' => $enPolicy],
            ]
        );
        $this->command->line('  <info>✓</info> Wedding Decoration Policy seeded');

        // ── About Page ──
        $this->command->info('--- Seeding About Page ---');

        $idAbout = [
            'text' => 'Wedding Flowers Organizer adalah aplikasi pendamping pernikahan digital yang membantu Anda merencanakan, mengatur, dan mewujudkan dekorasi bunga pernikahan impian Anda. Kami menyediakan berbagai pilihan paket dekorasi, produk bunga, dan layanan konsultasi yang dapat disesuaikan dengan tema dan anggaran pernikahan Anda.',
            'mission' => 'Misi kami adalah menjadi platform dekorasi bunga pernikahan terdepan di Indonesia yang menghubungkan pasangan dengan vendor dekorasi terpercaya, memberikan kemudahan dalam merencanakan dekorasi pernikahan, dan menciptakan pengalaman perencanaan pernikahan yang menyenangkan.',
        ];

        $enAbout = [
            'text' => 'Wedding Flowers Organizer is a digital wedding companion app that helps you plan, organize, and realize your dream wedding flower decoration. We provide various decoration package options, flower products, and consultation services customizable to your wedding theme and budget.',
            'mission' => 'Our mission is to become the leading wedding flower decoration platform in Indonesia that connects couples with trusted decoration vendors, provides convenience in planning wedding decorations, and creates an enjoyable wedding planning experience.',
        ];

        LegalPage::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'Tentang WeddingApp',
                'title_translations' => ['id' => 'Tentang WeddingApp', 'en' => 'About WeddingApp'],
                'content' => $idAbout,
                'content_translations' => ['id' => $idAbout, 'en' => $enAbout],
            ]
        );
        $this->command->line('  <info>✓</info> About Page seeded');

        $this->command->info('--- Terms & Privacy Seeding Complete ---');
    }
}
