<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    use TranslatesContent;

    public function run(): void
    {
        // Use system temp dir to avoid Windows permission issues with storage/framework/temp
        ini_set('sys_temp_dir', sys_get_temp_dir());
        putenv('TEMP=' . sys_get_temp_dir());
        putenv('TMP=' . sys_get_temp_dir());

        $this->command->info('--- Seeding All Catalogs (100 items) ---');

        $cats = Category::where('type', 'catalog')->get()->keyBy('slug');

        $catalogs = [
            // === ITEMS 1-50 ===
            [
                'name' => 'Gebyok Ukir Jati Premium',
                'category' => 'backdrop-pelaminan', 'price' => 15000000, 'discount_pct' => 10, 'stock' => 10,
                'description' => 'Gebyok ukir kayu jati pilihan dengan motif batik klasik. Cocok sebagai backdrop pelaminan adat Jawa yang megah.',
                'name_id' => 'Gebyok Ukir Jati Premium', 'name_en' => 'Premium Carved Teak Gebyok',
                'desc_id' => 'Gebyok ukir kayu jati pilihan dengan motif batik klasik. Cocok sebagai backdrop pelaminan adat Jawa yang megah.',
                'desc_en' => 'Selected teak wood carved gebyok with classic batik motifs. Perfect as a magnificent Javanese traditional wedding backdrop.',
            ],
            [
                'name' => 'Pelaminan Adat Jawa Mewah',
                'category' => 'backdrop-pelaminan', 'price' => 12000000, 'discount_pct' => 12, 'stock' => 8,
                'description' => 'Pelaminan adat Jawa lengkap dengan ukiran emas, kain songket, dan ornamen tradisional.',
                'name_id' => 'Pelaminan Adat Jawa Mewah', 'name_en' => 'Luxurious Javanese Traditional Throne',
                'desc_id' => 'Pelaminan adat Jawa lengkap dengan ukiran emas, kain songket, dan ornamen tradisional.',
                'desc_en' => 'Complete Javanese traditional throne with gold carvings, songket fabric, and traditional ornaments.',
            ],
            [
                'name' => 'Kain Batik Tulis Cendana',
                'category' => 'kain-dekorasi', 'price' => 3500000, 'discount_pct' => 15, 'stock' => 20,
                'description' => 'Batik tulis motif cendana dengan pewarna alami. Kain premium untuk dekorasi pernikahan adat.',
                'name_id' => 'Kain Batik Tulis Cendana', 'name_en' => 'Handwritten Sandalwood Batik Fabric',
                'desc_id' => 'Batik tulis motif cendana dengan pewarna alami. Kain premium untuk dekorasi pernikahan adat.',
                'desc_en' => 'Hand-drawn batik with sandalwood motif using natural dyes. Premium fabric for traditional wedding decor.',
            ],
            [
                'name' => 'Seserahan Pernikahan Adat',
                'category' => 'seserahan', 'price' => 5500000, 'discount_pct' => 10, 'stock' => 15,
                'description' => 'Set seserahan pernikahan adat lengkap 8 kotak dengan hiasan bunga dan kain batik.',
                'name_id' => 'Seserahan Pernikahan Adat', 'name_en' => 'Traditional Wedding Seserahan Set',
                'desc_id' => 'Set seserahan pernikahan adat lengkap 8 kotak dengan hiasan bunga dan kain batik.',
                'desc_en' => 'Complete traditional wedding seserahan set of 8 boxes with flower decorations and batik fabric.',
            ],
            [
                'name' => 'Backdrop Batik Mega Mendung',
                'category' => 'backdrop-pelaminan', 'price' => 4500000, 'discount_pct' => 12, 'stock' => 12,
                'description' => 'Backdrop besar dengan motif batik mega mendung khas Cirebon. Cocok untuk pelaminan adat.',
                'name_id' => 'Backdrop Batik Mega Mendung', 'name_en' => 'Mega Mendung Batik Backdrop',
                'desc_id' => 'Backdrop besar dengan motif batik mega mendung khas Cirebon. Cocok untuk pelaminan adat.',
                'desc_en' => 'Large backdrop with Cirebon mega mendung batik motif. Perfect for traditional wedding thrones.',
            ],
            [
                'name' => 'Chandelier Kristal Modern',
                'category' => 'lighting', 'price' => 20000000, 'discount_pct' => 10, 'stock' => 8,
                'description' => 'Chandelier kristal premium untuk dekorasi ballroom modern. Memantulkan cahaya indah di setiap sudut ruangan.',
                'name_id' => 'Chandelier Kristal Modern', 'name_en' => 'Modern Crystal Chandelier',
                'desc_id' => 'Chandelier kristal premium untuk dekorasi ballroom modern. Memantulkan cahaya indah di setiap sudut ruangan.',
                'desc_en' => 'Premium crystal chandelier for modern ballroom decoration. Reflects beautiful light in every corner.',
            ],
            [
                'name' => 'Neon Sign Custom Wedding',
                'category' => 'aksesoris', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 25,
                'description' => 'Neon sign custom dengan nama pasangan atau quote favorit. Dekorasi sekaligus kenang-kenangan.',
                'name_id' => 'Neon Sign Custom Wedding', 'name_en' => 'Custom Wedding Neon Sign',
                'desc_id' => 'Neon sign custom dengan nama pasangan atau quote favorit. Dekorasi sekaligus kenang-kenangan.',
                'desc_en' => 'Custom neon sign with couple names or favorite quotes. Serves as both decoration and keepsake.',
            ],
            [
                'name' => 'LED Backdrop Interaktif',
                'category' => 'lighting', 'price' => 8000000, 'discount_pct' => 11, 'stock' => 10,
                'description' => 'Backdrop LED interaktif dengan resolusi tinggi. Bisa menampilkan foto pasangan atau animasi.',
                'name_id' => 'LED Backdrop Interaktif', 'name_en' => 'Interactive LED Backdrop',
                'desc_id' => 'Backdrop LED interaktif dengan resolusi tinggi. Bisa menampilkan foto pasangan atau animasi.',
                'desc_en' => 'Interactive LED backdrop with high resolution. Can display couple photos or animations.',
            ],
            [
                'name' => 'Aisle Runner Premium Putih',
                'category' => 'kain-dekorasi', 'price' => 2500000, 'discount_pct' => 15, 'stock' => 30,
                'description' => 'Aisle runner premium putih dengan finishing mewah. Cocok untuk ballroom dan hotel.',
                'name_id' => 'Aisle Runner Premium Putih', 'name_en' => 'Premium White Aisle Runner',
                'desc_id' => 'Aisle runner premium putih dengan finishing mewah. Cocok untuk ballroom dan hotel.',
                'desc_en' => 'Premium white aisle runner with luxurious finish. Perfect for ballrooms and hotels.',
            ],
            [
                'name' => 'Geometric Backdrop Gold',
                'category' => 'ornamen-dinding', 'price' => 4200000, 'discount_pct' => 13, 'stock' => 15,
                'description' => 'Backdrop geometris dengan finishing gold. Desain modern dan elegan untuk foto utama.',
                'name_id' => 'Geometric Backdrop Gold', 'name_en' => 'Gold Geometric Backdrop',
                'desc_id' => 'Backdrop geometris dengan finishing gold. Desain modern dan elegan untuk foto utama.',
                'desc_en' => 'Geometric backdrop with gold finish. Modern and elegant design for main photos.',
            ],
            [
                'name' => 'Arch Kayu Rustic Natural',
                'category' => 'ornamen-dinding', 'price' => 8500000, 'discount_pct' => 12, 'stock' => 15,
                'description' => 'Arch kayu alami dengan finishing natural, dihiasi pampas grass dan bunga liar segar.',
                'name_id' => 'Arch Kayu Rustic Natural', 'name_en' => 'Natural Rustic Wood Arch',
                'desc_id' => 'Arch kayu alami dengan finishing natural, dihiasi pampas grass dan bunga liar segar.',
                'desc_en' => 'Natural wood arch with natural finish, decorated with pampas grass and fresh wildflowers.',
            ],
            [
                'name' => 'Macrame Bohemian Backdrop',
                'category' => 'ornamen-dinding', 'price' => 4500000, 'discount_pct' => 16, 'stock' => 12,
                'description' => 'Backdrop macrame handmade dengan sentuhan bohemian. Unik, artistik, dan penuh karakter.',
                'name_id' => 'Macrame Bohemian Backdrop', 'name_en' => 'Bohemian Macrame Backdrop',
                'desc_id' => 'Backdrop macrame handmade dengan sentuhan bohemian. Unik, artistik, dan penuh karakter.',
                'desc_en' => 'Handmade macrame backdrop with bohemian touch. Unique, artistic, and full of character.',
            ],
            [
                'name' => 'Pampas Grass Decoration Set',
                'category' => 'buket-bunga', 'price' => 1800000, 'discount_pct' => 18, 'stock' => 25,
                'description' => 'Set dekorasi pampas grass kering natural. Cocok untuk konsep rustic bohemian.',
                'name_id' => 'Set Dekorasi Pampas Grass', 'name_en' => 'Pampas Grass Decoration Set',
                'desc_id' => 'Set dekorasi pampas grass kering natural. Cocok untuk konsep rustic bohemian.',
                'desc_en' => 'Natural dried pampas grass decoration set. Perfect for rustic bohemian concepts.',
            ],
            [
                'name' => 'Fairy Light Curtain Warm',
                'category' => 'lighting', 'price' => 1200000, 'discount_pct' => 20, 'stock' => 40,
                'description' => 'Tirai fairy light warm white 3x3 meter. Menciptakan suasana hangat dan romantis.',
                'name_id' => 'Tirai Fairy Light Warm', 'name_en' => 'Warm Fairy Light Curtain',
                'desc_id' => 'Tirai fairy light warm white 3x3 meter. Menciptakan suasana hangat dan romantis.',
                'desc_en' => 'Warm white fairy light curtain 3x3 meters. Creates a warm and romantic atmosphere.',
            ],
            [
                'name' => 'Rustic Wooden Table Set',
                'category' => 'dekorasi-meja', 'price' => 3800000, 'discount_pct' => 14, 'stock' => 10,
                'description' => 'Set meja kayu rustic dengan finishing natural. Cocok untuk resepsi outdoor.',
                'name_id' => 'Set Meja Kayu Rustic', 'name_en' => 'Rustic Wooden Table Set',
                'desc_id' => 'Set meja kayu rustic dengan finishing natural. Cocok untuk resepsi outdoor.',
                'desc_en' => 'Rustic wooden table set with natural finish. Perfect for outdoor receptions.',
            ],
            [
                'name' => 'Flower Wall Blush Rose',
                'category' => 'ornamen-dinding', 'price' => 6500000, 'discount_pct' => 11, 'stock' => 20,
                'description' => 'Dinding bunga mawar blush pink yang memukau. Menjadi spot foto favorit tamu undangan.',
                'name_id' => 'Flower Wall Blush Rose', 'name_en' => 'Blush Rose Flower Wall',
                'desc_id' => 'Dinding bunga mawar blush pink yang memukau. Menjadi spot foto favorit tamu undangan.',
                'desc_en' => 'Stunning blush pink rose flower wall. Becomes guests favorite photo spot.',
            ],
            [
                'name' => 'Greenery Wall Skandinavia',
                'category' => 'ornamen-dinding', 'price' => 5500000, 'discount_pct' => 9, 'stock' => 18,
                'description' => 'Dinding hijau segar bergaya Skandinavia dengan tanaman pilihan. Bersih, natural, dan timeless.',
                'name_id' => 'Greenery Wall Skandinavia', 'name_en' => 'Scandinavian Greenery Wall',
                'desc_id' => 'Dinding hijau segar bergaya Skandinavia dengan tanaman pilihan. Bersih, natural, dan timeless.',
                'desc_en' => 'Fresh green wall in Scandinavian style with selected plants. Clean, natural, and timeless.',
            ],
            [
                'name' => 'Marble Ceremony Table',
                'category' => 'dekorasi-meja', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 12,
                'description' => 'Meja akad nikah marble putih dengan kaki gold minimalis. Elegan dan fotogenik.',
                'name_id' => 'Meja Akad Marble Putih', 'name_en' => 'White Marble Ceremony Table',
                'desc_id' => 'Meja akad nikah marble putih dengan kaki gold minimalis. Elegan dan fotogenik.',
                'desc_en' => 'White marble wedding ceremony table with minimalist gold legs. Elegant and photogenic.',
            ],
            [
                'name' => 'Eucalyptus Garland Set',
                'category' => 'buket-bunga', 'price' => 1500000, 'discount_pct' => 18, 'stock' => 30,
                'description' => 'Set garland eucalyptus segar untuk dekorasi meja dan arch. Aroma segar dan tampilan natural.',
                'name_id' => 'Set Garland Eucalyptus', 'name_en' => 'Eucalyptus Garland Set',
                'desc_id' => 'Set garland eucalyptus segar untuk dekorasi meja dan arch. Aroma segar dan tampilan natural.',
                'desc_en' => 'Fresh eucalyptus garland set for table and arch decoration. Fresh scent and natural look.',
            ],
            [
                'name' => 'Acrylic Sign Holder Gold',
                'category' => 'aksesoris', 'price' => 850000, 'discount_pct' => 20, 'stock' => 40,
                'description' => 'Sign holder akrilik dengan kaki gold. Untuk menu, welcome sign, atau table numbers.',
                'name_id' => 'Akrilik Sign Holder Gold', 'name_en' => 'Gold Acrylic Sign Holder',
                'desc_id' => 'Sign holder akrilik dengan kaki gold. Untuk menu, welcome sign, atau table numbers.',
                'desc_en' => 'Acrylic sign holder with gold legs. For menus, welcome signs, or table numbers.',
            ],
            [
                'name' => 'Pergola Taman Inggris',
                'category' => 'dekorasi-meja', 'price' => 12000000, 'discount_pct' => 10, 'stock' => 6,
                'description' => 'Pergola bergaya taman Inggris dengan ivy dan mawar garden. Suasana romantis di outdoor.',
                'name_id' => 'Pergola Taman Inggris', 'name_en' => 'English Garden Pergola',
                'desc_id' => 'Pergola bergaya taman Inggris dengan ivy dan mawar garden. Suasana romantis di outdoor.',
                'desc_en' => 'English garden style pergola with ivy and garden roses. Romantic outdoor atmosphere.',
            ],
            [
                'name' => 'Floral Arch Large Garden',
                'category' => 'ornamen-dinding', 'price' => 7800000, 'discount_pct' => 12, 'stock' => 8,
                'description' => 'Arch bunga besar untuk taman dengan kombinasi mawar, hydrangea, dan greenery.',
                'name_id' => 'Arch Bunga Besar Taman', 'name_en' => 'Large Garden Floral Arch',
                'desc_id' => 'Arch bunga besar untuk taman dengan kombinasi mawar, hydrangea, dan greenery.',
                'desc_en' => 'Large floral arch for garden with combination of roses, hydrangeas, and greenery.',
            ],
            [
                'name' => 'Gazebo Putih Klasik',
                'category' => 'dekorasi-meja', 'price' => 15000000, 'discount_pct' => 8, 'stock' => 4,
                'description' => 'Gazebo putih klasik dengan ornamen besi tempa. Cocok sebagai lokasi akad outdoor.',
                'name_id' => 'Gazebo Putih Klasik', 'name_en' => 'Classic White Gazebo',
                'desc_id' => 'Gazebo putih klasik dengan ornamen besi tempa. Cocok sebagai lokasi akad outdoor.',
                'desc_en' => 'Classic white gazebo with wrought iron ornaments. Perfect for outdoor ceremony location.',
            ],
            [
                'name' => 'Ivy Greenery Wall Panel',
                'category' => 'ornamen-dinding', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 20,
                'description' => 'Panel dinding ivy buatan 1x2 meter. Mudah dipasang untuk dekorasi taman vertikal.',
                'name_id' => 'Panel Dinding Ivy Hijau', 'name_en' => 'Ivy Greenery Wall Panel',
                'desc_id' => 'Panel dinding ivy buatan 1x2 meter. Mudah dipasang untuk dekorasi taman vertikal.',
                'desc_en' => 'Artificial ivy wall panel 1x2 meters. Easy to install for vertical garden decoration.',
            ],
            [
                'name' => 'Garden Candle Lantern Set',
                'category' => 'lighting', 'price' => 1600000, 'discount_pct' => 18, 'stock' => 25,
                'description' => 'Set lampion lilin taman 6 pcs. Menciptakan suasana hangat di resepsi outdoor.',
                'name_id' => 'Set Lampion Lilin Taman', 'name_en' => 'Garden Candle Lantern Set',
                'desc_id' => 'Set lampion lilin taman 6 pcs. Menciptakan suasana hangat di resepsi outdoor.',
                'desc_en' => 'Set of 6 garden candle lanterns. Creates a warm atmosphere at outdoor receptions.',
            ],
            [
                'name' => 'Pelaminan Emas Royal',
                'category' => 'backdrop-pelaminan', 'price' => 35000000, 'discount_pct' => 9, 'stock' => 3,
                'description' => 'Pelaminan mewah berlapis emas dengan ornamen kerajaan. Untuk pernikahan yang benar-benar berkesan.',
                'name_id' => 'Pelaminan Emas Royal', 'name_en' => 'Royal Gold Wedding Throne',
                'desc_id' => 'Pelaminan mewah berlapis emas dengan ornamen kerajaan. Untuk pernikahan yang benar-benar berkesan.',
                'desc_en' => 'Luxurious gold-plated wedding throne with royal ornaments. For a truly memorable wedding.',
            ],
            [
                'name' => 'Candelabra Set Mewah',
                'category' => 'aksesoris', 'price' => 9000000, 'discount_pct' => 11, 'stock' => 8,
                'description' => 'Set candelabra emas mewah untuk dekorasi meja dan aisle. Kesan elegan dan dramatis.',
                'name_id' => 'Candelabra Set Mewah', 'name_en' => 'Luxury Candelabra Set',
                'desc_id' => 'Set candelabra emas mewah untuk dekorasi meja dan aisle. Kesan elegan dan dramatis.',
                'desc_en' => 'Luxurious gold candelabra set for table and aisle decoration. Elegant and dramatic.',
            ],
            [
                'name' => 'Ceiling Draping Velvet Royal',
                'category' => 'kain-dekorasi', 'price' => 6500000, 'discount_pct' => 12, 'stock' => 8,
                'description' => 'Draping ceiling velvet merah premium dengan aksen gold. Untuk ballroom megah.',
                'name_id' => 'Ceiling Draping Velvet Royal', 'name_en' => 'Royal Velvet Ceiling Draping',
                'desc_id' => 'Draping ceiling velvet merah premium dengan aksen gold. Untuk ballroom megah.',
                'desc_en' => 'Premium red velvet ceiling draping with gold accents. For a magnificent ballroom.',
            ],
            [
                'name' => 'Red Carpet VIP Set',
                'category' => 'kain-dekorasi', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 15,
                'description' => 'Set red carpet VIP dengan stanchion gold dan rope barrier. Untuk penyambutan tamu.',
                'name_id' => 'Set Red Carpet VIP', 'name_en' => 'VIP Red Carpet Set',
                'desc_id' => 'Set red carpet VIP dengan stanchion gold dan rope barrier. Untuk penyambutan tamu.',
                'desc_en' => 'VIP red carpet set with gold stanchions and rope barrier. For guest reception.',
            ],
            [
                'name' => 'Royal Gold Table Centerpiece',
                'category' => 'dekorasi-meja', 'price' => 2200000, 'discount_pct' => 16, 'stock' => 20,
                'description' => 'Centerpiece meja emas royal dengan bunga mawar merah dan kristal.',
                'name_id' => 'Centerpiece Emas Royal', 'name_en' => 'Royal Gold Table Centerpiece',
                'desc_id' => 'Centerpiece meja emas royal dengan bunga mawar merah dan kristal.',
                'desc_en' => 'Royal gold table centerpiece with red roses and crystals.',
            ],
            [
                'name' => 'Beachfront Altar Arch',
                'category' => 'ornamen-dinding', 'price' => 7500000, 'discount_pct' => 12, 'stock' => 8,
                'description' => 'Arch altar untuk pernikahan tepi pantai dengan draping putih dan ornamen kerang.',
                'name_id' => 'Arch Altar Tepi Pantai', 'name_en' => 'Beachfront Altar Arch',
                'desc_id' => 'Arch altar untuk pernikahan tepi pantai dengan draping putih dan ornamen kerang.',
                'desc_en' => 'Altar arch for beachfront wedding with white draping and shell ornaments.',
            ],
            [
                'name' => 'Frangipani Flower Garland',
                'category' => 'hiasan-gaun', 'price' => 1200000, 'discount_pct' => 20, 'stock' => 30,
                'description' => 'Garland bunga frangipani segar untuk dekorasi leher atau meja. Wangi khas tropis.',
                'name_id' => 'Garland Bunga Frangipani', 'name_en' => 'Frangipani Flower Garland',
                'desc_id' => 'Garland bunga frangipani segar untuk dekorasi leher atau meja. Wangi khas tropis.',
                'desc_en' => 'Fresh frangipani flower garland for neck or table decoration. Distinct tropical scent.',
            ],
            [
                'name' => 'Tiki Torch Set Outdoor',
                'category' => 'lighting', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 15,
                'description' => 'Set tiki torch bambu 6 pcs untuk lighting pernikahan pantai.',
                'name_id' => 'Set Tiki Torch Outdoor', 'name_en' => 'Outdoor Tiki Torch Set',
                'desc_id' => 'Set tiki torch bambu 6 pcs untuk lighting pernikahan pantai.',
                'desc_en' => 'Set of 6 bamboo tiki torches for beach wedding lighting.',
            ],
            [
                'name' => 'Starfish Shell Centerpiece',
                'category' => 'dekorasi-meja', 'price' => 950000, 'discount_pct' => 22, 'stock' => 35,
                'description' => 'Centerpiece meja dengan bintang laut, kerang, dan pasir putih. Tema pantai otentik.',
                'name_id' => 'Centerpiece Bintang Laut', 'name_en' => 'Starfish Shell Centerpiece',
                'desc_id' => 'Centerpiece meja dengan bintang laut, kerang, dan pasir putih. Tema pantai otentik.',
                'desc_en' => 'Table centerpiece with starfish, shells, and white sand. Authentic beach theme.',
            ],
            [
                'name' => 'White Muslin Draping Set',
                'category' => 'kain-dekorasi', 'price' => 2100000, 'discount_pct' => 18, 'stock' => 20,
                'description' => 'Set draping muslin putih 5 meter untuk dekorasi altar pantai yang elegan.',
                'name_id' => 'Set Draping Muslin Putih', 'name_en' => 'White Muslin Draping Set',
                'desc_id' => 'Set draping muslin putih 5 meter untuk dekorasi altar pantai yang elegan.',
                'desc_en' => '5-meter white muslin draping set for elegant beach altar decoration.',
            ],
            [
                'name' => 'Vintage Furniture Set Parlor',
                'category' => 'dekorasi-meja', 'price' => 8500000, 'discount_pct' => 11, 'stock' => 6,
                'description' => 'Set furnitur vintage parlor terdiri dari sofa, meja, dan kursi gaya Perancis.',
                'name_id' => 'Set Furnitur Vintage Parlor', 'name_en' => 'Vintage Parlor Furniture Set',
                'desc_id' => 'Set furnitur vintage parlor terdiri dari sofa, meja, dan kursi gaya Perancis.',
                'desc_en' => 'Vintage parlor furniture set consisting of sofa, table, and French-style chairs.',
            ],
            [
                'name' => 'Antique Gold Mirror Decor',
                'category' => 'ornamen-dinding', 'price' => 3800000, 'discount_pct' => 14, 'stock' => 10,
                'description' => 'Cermin antik dengan bingkai emas ukir. Dekorasi elegan untuk photo booth backdrop.',
                'name_id' => 'Cermin Antik Bingkai Emas', 'name_en' => 'Antique Gold Mirror Decor',
                'desc_id' => 'Cermin antik dengan bingkai emas ukir. Dekorasi elegan untuk photo booth backdrop.',
                'desc_en' => 'Antique mirror with carved gold frame. Elegant decor for photo booth backdrop.',
            ],
            [
                'name' => 'Lace Table Runner Premium',
                'category' => 'kain-dekorasi', 'price' => 850000, 'discount_pct' => 20, 'stock' => 30,
                'description' => 'Table runner renda premium panjang 2 meter. Sentuhan vintage yang lembut.',
                'name_id' => 'Table Runner Renda Premium', 'name_en' => 'Premium Lace Table Runner',
                'desc_id' => 'Table runner renda premium panjang 2 meter. Sentuhan vintage yang lembut.',
                'desc_en' => 'Premium lace table runner 2 meters long. A soft vintage touch.',
            ],
            [
                'name' => 'Vintage Candle Chandelier',
                'category' => 'lighting', 'price' => 4200000, 'discount_pct' => 13, 'stock' => 8,
                'description' => 'Chandelier lilin vintage dengan ornamen besi tempa. Cocok untuk tema vintage klasik.',
                'name_id' => 'Chandelier Lilin Vintage', 'name_en' => 'Vintage Candle Chandelier',
                'desc_id' => 'Chandelier lilin vintage dengan ornamen besi tempa. Cocok untuk tema vintage klasik.',
                'desc_en' => 'Vintage candle chandelier with wrought iron ornaments. Perfect for classic vintage theme.',
            ],
            [
                'name' => 'Pastel Balloon Arch Set',
                'category' => 'aksesoris', 'price' => 1900000, 'discount_pct' => 18, 'stock' => 20,
                'description' => 'Set arch balon pastel warna vintage rose, lavender, dan cream. Manis dan romantis.',
                'name_id' => 'Set Arch Balon Pastel', 'name_en' => 'Pastel Balloon Arch Set',
                'desc_id' => 'Set arch balon pastel warna vintage rose, lavender, dan cream. Manis dan romantis.',
                'desc_en' => 'Pastel balloon arch set in vintage rose, lavender, and cream colors. Sweet and romantic.',
            ],
            [
                'name' => 'Pipa Metal Pipe Rack',
                'category' => 'dekorasi-meja', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 12,
                'description' => 'Rak pipa besi industrial untuk dekorasi atau display bunga. Kuat dan unik.',
                'name_id' => 'Rak Pipa Besi Industrial', 'name_en' => 'Metal Pipe Rack Industrial',
                'desc_id' => 'Rak pipa besi industrial untuk dekorasi atau display bunga. Kuat dan unik.',
                'desc_en' => 'Industrial metal pipe rack for decoration or flower display. Sturdy and unique.',
            ],
            [
                'name' => 'Exposed Brick Wall Panel',
                'category' => 'ornamen-dinding', 'price' => 2500000, 'discount_pct' => 17, 'stock' => 15,
                'description' => 'Panel dinding bata ekspos artifisial 1x2 meter. Untuk konsep industrial loft.',
                'name_id' => 'Panel Dinding Bata Ekspos', 'name_en' => 'Exposed Brick Wall Panel',
                'desc_id' => 'Panel dinding bata ekspos artifisial 1x2 meter. Untuk konsep industrial loft.',
                'desc_en' => 'Artificial exposed brick wall panel 1x2 meters. For industrial loft concept.',
            ],
            [
                'name' => 'Edison Bulb String Light',
                'category' => 'lighting', 'price' => 1400000, 'discount_pct' => 20, 'stock' => 30,
                'description' => 'String light edison bulb 10 lampu. Lighting hangat bergaya industrial vintage.',
                'name_id' => 'String Light Edison Bulb', 'name_en' => 'Edison Bulb String Light',
                'desc_id' => 'String light edison bulb 10 lampu. Lighting hangat bergaya industrial vintage.',
                'desc_en' => 'Edison bulb string light with 10 bulbs. Warm industrial vintage style lighting.',
            ],
            [
                'name' => 'Wooden Crate Table Set',
                'category' => 'dekorasi-meja', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 15,
                'description' => 'Set meja dari kayu crate industrial. Tinggi rendah, cocok untuk konsep raw.',
                'name_id' => 'Set Meja Kayu Crate', 'name_en' => 'Wooden Crate Table Set',
                'desc_id' => 'Set meja dari kayu crate industrial. Tinggi rendah, cocok untuk konsep raw.',
                'desc_en' => 'Table set from industrial wooden crates. Various heights, perfect for raw concept.',
            ],
            [
                'name' => 'Steel Grid Photo Display',
                'category' => 'ornamen-dinding', 'price' => 1600000, 'discount_pct' => 19, 'stock' => 20,
                'description' => 'Display foto grid besi dengan clip dan string. Untuk galeri foto pasangan.',
                'name_id' => 'Display Foto Grid Besi', 'name_en' => 'Steel Grid Photo Display',
                'desc_id' => 'Display foto grid besi dengan clip dan string. Untuk galeri foto pasangan.',
                'desc_en' => 'Steel grid photo display with clips and strings. For couple photo gallery.',
            ],
            [
                'name' => 'Bird of Paradise Flower Set',
                'category' => 'buket-bunga', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 12,
                'description' => 'Set rangkaian bunga bird of paradise segar. Ikonik untuk dekorasi tropis.',
                'name_id' => 'Set Bunga Bird of Paradise', 'name_en' => 'Bird of Paradise Flower Set',
                'desc_id' => 'Set rangkaian bunga bird of paradise segar. Ikonik untuk dekorasi tropis.',
                'desc_en' => 'Fresh bird of paradise flower arrangement set. Iconic for tropical decor.',
            ],
            [
                'name' => 'Monstera Leaf Decoration',
                'category' => 'tanaman-hias', 'price' => 1100000, 'discount_pct' => 20, 'stock' => 25,
                'description' => 'Daun monstera artificial premium untuk dekorasi meja dan backdrop. Ukuran besar.',
                'name_id' => 'Dekorasi Daun Monstera', 'name_en' => 'Monstera Leaf Decoration',
                'desc_id' => 'Daun monstera artificial premium untuk dekorasi meja dan backdrop. Ukuran besar.',
                'desc_en' => 'Premium artificial monstera leaves for table and backdrop decoration. Large size.',
            ],
            [
                'name' => 'Bali Stone Carving Ornament',
                'category' => 'ornamen-dinding', 'price' => 4500000, 'discount_pct' => 13, 'stock' => 8,
                'description' => 'Ornamen ukiran batu Bali untuk dekorasi taman tropis. Motif khas Bali.',
                'name_id' => 'Ornamen Ukiran Batu Bali', 'name_en' => 'Bali Stone Carving Ornament',
                'desc_id' => 'Ornamen ukiran batu Bali untuk dekorasi taman tropis. Motif khas Bali.',
                'desc_en' => 'Balinese stone carving ornament for tropical garden decor. Distinctive Balinese motifs.',
            ],
            [
                'name' => 'Tropical Palm Leaf Arch',
                'category' => 'ornamen-dinding', 'price' => 5800000, 'discount_pct' => 12, 'stock' => 8,
                'description' => 'Arch daun palem tropis dengan bunga heliconia dan ginger. Warna-warni ceria.',
                'name_id' => 'Arch Daun Palem Tropis', 'name_en' => 'Tropical Palm Leaf Arch',
                'desc_id' => 'Arch daun palem tropis dengan bunga heliconia dan ginger. Warna-warni ceria.',
                'desc_en' => 'Tropical palm leaf arch with heliconia and ginger flowers. Cheerful and colorful.',
            ],
            [
                'name' => 'Bamboo Water Fountain',
                'category' => 'tanaman-hias', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 10,
                'description' => 'Air mancur bambu untuk dekorasi taman tropis. Suara gemericik air yang menenangkan.',
                'name_id' => 'Air Mancur Bambu', 'name_en' => 'Bamboo Water Fountain',
                'desc_id' => 'Air mancur bambu untuk dekorasi taman tropis. Suara gemericik air yang menenangkan.',
                'desc_en' => 'Bamboo water fountain for tropical garden decoration. Calming water trickling sound.',
            ],
            // === ITEMS 51-100 (Flower Decoration Packages) ===
            [
                'name' => 'Standard Flower Decoration Package',
                'category' => null, 'price' => 15000000, 'discount_pct' => 10, 'stock' => 10,
                'features' => ['Dekorasi bunga standar', 'Bunga segar pilihan', 'Backdrop sederhana', 'Dekorasi meja tamu', 'Setup dan breakdown'],
                'description' => 'Paket dekorasi bunga standar untuk pernikahan sederhana namun elegan. Termasuk bunga segar pilihan dan backdrop sederhana.',
                'name_id' => 'Paket Dekorasi Bunga Standar', 'name_en' => 'Standard Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga standar untuk pernikahan sederhana namun elegan. Termasuk bunga segar pilihan dan backdrop sederhana.',
                'desc_en' => 'Standard flower decoration package for simple yet elegant weddings. Includes selected fresh flowers and simple backdrop.',
            ],
            [
                'name' => 'Medium Flower Decoration Package',
                'category' => null, 'price' => 25000000, 'discount_pct' => 12, 'stock' => 8,
                'features' => ['Dekorasi bunga medium', 'Bunga premium segar', 'Backdrop pelaminan', 'Dekorasi meja lengkap', 'Aisle decoration', 'Setup dan breakdown'],
                'description' => 'Paket dekorasi bunga medium dengan bunga premium segar, backdrop pelaminan, dan dekorasi meja lengkap.',
                'name_id' => 'Paket Dekorasi Bunga Medium', 'name_en' => 'Medium Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga medium dengan bunga premium segar, backdrop pelaminan, dan dekorasi meja lengkap.',
                'desc_en' => 'Medium flower decoration package with premium fresh flowers, wedding backdrop, and complete table decoration.',
            ],
            [
                'name' => 'Ceremony or Wedding Flower Decoration Package',
                'category' => null, 'price' => 35000000, 'discount_pct' => 15, 'stock' => 5,
                'features' => ['Dekorasi upacara lengkap', 'Bunga premium import', 'Pelaminan mewah', 'Dekorasi aisle penuh', 'Dekorasi meja tamu VIP', 'Flower girl bouquet', 'Setup dan breakdown'],
                'description' => 'Paket dekorasi bunga lengkap untuk upacara atau pernikahan dengan bunga premium import dan pelaminan mewah.',
                'name_id' => 'Paket Dekorasi Bunga Upacara atau Pernikahan', 'name_en' => 'Ceremony or Wedding Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga lengkap untuk upacara atau pernikahan dengan bunga premium import dan pelaminan mewah.',
                'desc_en' => 'Complete flower decoration package for ceremony or wedding with imported premium flowers and luxurious throne.',
            ],
            [
                'name' => 'Engagement Flower Decoration Package',
                'category' => null, 'price' => 12000000, 'discount_pct' => 8, 'stock' => 10,
                'features' => ['Dekorasi lamaran', 'Bunga segar pilihan', 'Backdrop minimalis', 'Dekorasi meja lamaran', 'Bouquet pengantin'],
                'description' => 'Paket dekorasi bunga untuk acara lamaran dengan bunga segar pilihan dan backdrop minimalis yang elegan.',
                'name_id' => 'Paket Dekorasi Bunga Lamaran', 'name_en' => 'Engagement Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga untuk acara lamaran dengan bunga segar pilihan dan backdrop minimalis yang elegan.',
                'desc_en' => 'Flower decoration package for engagement events with selected fresh flowers and elegant minimalist backdrop.',
            ],
            [
                'name' => 'Full Flower Decoration Package',
                'category' => null, 'price' => 50000000, 'discount_pct' => 18, 'stock' => 3,
                'features' => ['Dekorasi bunga penuh', 'Bunga premium import eksklusif', 'Pelaminan megah', 'Dekorasi aisle penuh', 'Dekorasi meja tamu lengkap', 'Flower wall backdrop', 'Chandelier bunga', 'Setup dan breakdown profesional'],
                'description' => 'Paket dekorasi bunga penuh dan lengkap untuk pernikahan impian dengan bunga premium import eksklusif dan pelaminan megah.',
                'name_id' => 'Paket Dekorasi Bunga Lengkap', 'name_en' => 'Full Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga penuh dan lengkap untuk pernikahan impian dengan bunga premium import eksklusif dan pelaminan megah.',
                'desc_en' => 'Complete full flower decoration package for dream weddings with exclusive imported premium flowers and magnificent throne.',
            ],
            [
                'name' => 'Modern Crystal Stage Package',
                'category' => null, 'price' => 25000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Chandelier kristal', 'Bunga anggrek putih', 'Backdrop LED', 'Karpet merah premium', 'Meja penerima tamu'],
                'description' => 'Paket dekorasi modern elegan dengan chandelier kristal berkilau dan rangkaian anggrek putih. Backdrop LED interaktif menciptakan suasana mewah.',
                'name_id' => 'Paket Panggung Kristal Modern', 'name_en' => 'Modern Crystal Stage Package',
                'desc_id' => 'Paket dekorasi modern elegan dengan chandelier kristal berkilau dan rangkaian anggrek putih. Backdrop LED interaktif menciptakan suasana mewah.',
                'desc_en' => 'Elegant modern decoration package with sparkling crystal chandeliers and white orchid arrangements. Interactive LED backdrop creates a luxurious atmosphere.',
            ],
            [
                'name' => 'Contemporary White Luxe Package',
                'category' => null, 'price' => 27000000, 'discount_pct' => 7, 'stock' => 5,
                'features' => ['All-white concept', 'Bunga peony & mawar', 'Neon sign custom', 'Flower wall backdrop', 'Aisle dekorasi'],
                'description' => 'Konsep serba putih yang bersih dan mewah dengan bunga peony dan mawar pilihan. Neon sign custom dan flower wall backdrop.',
                'name_id' => 'Paket Mewah Putih Kontemporer', 'name_en' => 'Contemporary White Luxe Package',
                'desc_id' => 'Konsep serba putih yang bersih dan mewah dengan bunga peony dan mawar pilihan. Neon sign custom dan flower wall backdrop.',
                'desc_en' => 'Clean and luxurious all-white concept with peonies and premium roses. Custom neon sign and flower wall backdrop.',
            ],
            [
                'name' => 'Sleek Urban Loft Package',
                'category' => null, 'price' => 22000000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Konsep loft industrial', 'Bunga monokrom', 'Geometric backdrop', 'Furniture modern', 'String lighting'],
                'description' => 'Gaya urban loft yang sleek dengan warna monokrom, geometric backdrop, dan string lighting yang hangat.',
                'name_id' => 'Paket Urban Loft Modern', 'name_en' => 'Sleek Urban Loft Package',
                'desc_id' => 'Gaya urban loft yang sleek dengan warna monokrom, geometric backdrop, dan string lighting yang hangat.',
                'desc_en' => 'Sleek urban loft style with monochrome colors, geometric backdrop, and warm string lighting.',
            ],
            [
                'name' => 'Futuristic Glass Palace Package',
                'category' => null, 'price' => 32000000, 'discount_pct' => 6, 'stock' => 3,
                'features' => ['Dinding kaca premium', 'LED pixel mapping', 'Bunga white orchid', 'Floating decor', 'Laser lighting show'],
                'description' => 'Konsep futuristik dengan dinding kaca dan LED pixel mapping. Tampilan visual yang memukau dan modern.',
                'name_id' => 'Paket Istana Kaca Futuristik', 'name_en' => 'Futuristic Glass Palace Package',
                'desc_id' => 'Konsep futuristik dengan dinding kaca dan LED pixel mapping. Tampilan visual yang memukau dan modern.',
                'desc_en' => 'Futuristic concept with glass walls and LED pixel mapping. Stunning and modern visual display.',
            ],
            [
                'name' => 'Minimalist Monochrome Package',
                'category' => null, 'price' => 19000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Monochrome concept', 'Bunga white anemone', 'Marble backdrop', 'Clean line furniture', 'Modern chandelier'],
                'description' => 'Keindahan monokrom yang timeless dengan white anemone, marble backdrop, dan furnitur clean line.',
                'name_id' => 'Paket Monokrom Minimalis', 'name_en' => 'Minimalist Monochrome Package',
                'desc_id' => 'Keindahan monokrom yang timeless dengan white anemone, marble backdrop, dan furnitur clean line.',
                'desc_en' => 'Timeless monochrome beauty with white anemones, marble backdrop, and clean line furniture.',
            ],
            [
                'name' => 'Rustic Sunset Garden Package',
                'category' => null, 'price' => 17000000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Arch kayu alami', 'Pampas grass', 'Fairy lights', 'Bunga liar segar', 'Dekorasi bambu'],
                'description' => 'Paket dekorasi rustic hangat dengan arch kayu alami, pampas grass, dan fairy lights yang romantis.',
                'name_id' => 'Paket Taman Senja Rustic', 'name_en' => 'Rustic Sunset Garden Package',
                'desc_id' => 'Paket dekorasi rustic hangat dengan arch kayu alami, pampas grass, dan fairy lights yang romantis.',
                'desc_en' => 'Warm rustic decoration package with natural wooden arch, pampas grass, and romantic fairy lights.',
            ],
            [
                'name' => 'Bohemian Wildflower Dream Package',
                'category' => null, 'price' => 18500000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Macrame backdrop', 'Bunga liar mix', 'Tipi tent dekorasi', 'Dreamcatcher ornamen', 'Karpet etnik'],
                'description' => 'Gaya bohemian bebas dengan macrame backdrop, bunga liar warna-warni, dan ornamen dreamcatcher.',
                'name_id' => 'Paket Impian Bunga Liar Bohemian', 'name_en' => 'Bohemian Wildflower Dream Package',
                'desc_id' => 'Gaya bohemian bebas dengan macrame backdrop, bunga liar warna-warni, dan ornamen dreamcatcher.',
                'desc_en' => 'Free-spirited bohemian style with macrame backdrop, colorful wildflowers, and dreamcatcher ornaments.',
            ],
            [
                'name' => 'Barnwood Countryside Package',
                'category' => null, 'price' => 15500000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Barnwood backdrop', 'Bunga sunflower mix', 'Wheel barrel decor', 'Hay bale seating', 'Jute aisle runner'],
                'description' => 'Nuansa pedesaan Eropa dengan barnwood, bunga sunflower, dan dekorasi wheel barrel yang unik.',
                'name_id' => 'Paket Pedesaan Barnwood', 'name_en' => 'Barnwood Countryside Package',
                'desc_id' => 'Nuansa pedesaan Eropa dengan barnwood, bunga sunflower, dan dekorasi wheel barrel yang unik.',
                'desc_en' => 'European countryside ambiance with barnwood, sunflowers, and unique wheel barrel decorations.',
            ],
            [
                'name' => 'Vineyard Twilight Package',
                'category' => null, 'price' => 21000000, 'discount_pct' => 7, 'stock' => 4,
                'features' => ['Pergola anggur', 'Bunga lavender', 'Wine barrel decor', 'String lights warm', 'Oak table rustic'],
                'description' => 'Pernikahan romantis di kebun anggur senja dengan pergola, lavender, dan wine barrel decor.',
                'name_id' => 'Paket Senja Kebun Anggur', 'name_en' => 'Vineyard Twilight Package',
                'desc_id' => 'Pernikahan romantis di kebun anggur senja dengan pergola, lavender, dan wine barrel decor.',
                'desc_en' => 'Romantic vineyard twilight wedding with pergola, lavender, and wine barrel decor.',
            ],
            [
                'name' => 'Rustic Meadow Picnic Package',
                'category' => null, 'price' => 14000000, 'discount_pct' => 11, 'stock' => 5,
                'features' => ['Picnic setup outdoor', 'Bunga wildflower mix', 'Piknik basket decor', 'Karpet tartan', 'Pillow boho'],
                'description' => 'Pernikahan santai bergaya piknik di padang rumput dengan dekorasi boho dan bunga liar.',
                'name_id' => 'Paket Piknik Padang Rumput', 'name_en' => 'Rustic Meadow Picnic Package',
                'desc_id' => 'Pernikahan santai bergaya piknik di padang rumput dengan dekorasi boho dan bunga liar.',
                'desc_en' => 'Casual picnic-style wedding in the meadow with boho decor and wildflowers.',
            ],
            [
                'name' => 'Minimalist Pastel Arch Package',
                'category' => null, 'price' => 13000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Arch geometris', 'Bunga blush rose', 'Eucalyptus garland', 'Backdrop polos premium', 'Dekorasi meja simpel'],
                'description' => 'Paket dekorasi minimalis bersih dengan arch geometris, blush rose, dan eucalyptus.',
                'name_id' => 'Paket Arch Pastel Minimalis', 'name_en' => 'Minimalist Pastel Arch Package',
                'desc_id' => 'Paket dekorasi minimalis bersih dengan arch geometris, blush rose, dan eucalyptus.',
                'desc_en' => 'Clean minimalist decoration package with geometric arch, blush roses, and eucalyptus.',
            ],
            [
                'name' => 'Scandinavian Simplicity Package',
                'category' => null, 'price' => 15500000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Konsep Skandinavia', 'Bunga dried flower', 'Furniture kayu ringan', 'Textile natural', 'Lighting warm'],
                'description' => 'Desain Skandinavia yang hangat dengan dried flower, kayu ringan, dan tekstil natural.',
                'name_id' => 'Paket Simpel Skandinavia', 'name_en' => 'Scandinavian Simplicity Package',
                'desc_id' => 'Desain Skandinavia yang hangat dengan dried flower, kayu ringan, dan tekstil natural.',
                'desc_en' => 'Warm Scandinavian design with dried flowers, light wood, and natural textiles.',
            ],
            [
                'name' => 'Zen Minimalist Garden Package',
                'category' => null, 'price' => 17500000, 'discount_pct' => 7, 'stock' => 4,
                'features' => ['Konsep Zen', 'Bunga cherry blossom', 'Bamboo decor', 'Stone pathway', 'Water element'],
                'description' => 'Ketenangan taman Zen dengan cherry blossom, bambu, dan elemen air yang menenangkan.',
                'name_id' => 'Paket Taman Zen Minimalis', 'name_en' => 'Zen Minimalist Garden Package',
                'desc_id' => 'Ketenangan taman Zen dengan cherry blossom, bambu, dan elemen air yang menenangkan.',
                'desc_en' => 'Tranquil Zen garden with cherry blossoms, bamboo, and calming water elements.',
            ],
            [
                'name' => 'Clean White Sanctuary Package',
                'category' => null, 'price' => 20000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['All-white floral', 'Bunga white lily', 'White draping', 'Marble accent', 'Gold geometric'],
                'description' => 'Pure white sanctuary dengan white lily, marble accent, dan gold geometric yang elegan.',
                'name_id' => 'Paket Suaka Putih Bersih', 'name_en' => 'Clean White Sanctuary Package',
                'desc_id' => 'Pure white sanctuary dengan white lily, marble accent, dan gold geometric yang elegan.',
                'desc_en' => 'Pure white sanctuary with white lilies, marble accents, and elegant gold geometrics.',
            ],
            [
                'name' => 'Boho Minimalist Terrace Package',
                'category' => null, 'price' => 14500000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Boho chic style', 'Bunga pampas & lavender', 'Rattan furniture', 'Macrame accent', 'Candle lantern'],
                'description' => 'Terrace boho minimalis dengan pampas, lavender, dan furnitur rotan yang santai.',
                'name_id' => 'Paket Teras Boho Minimalis', 'name_en' => 'Boho Minimalist Terrace Package',
                'desc_id' => 'Terrace boho minimalis dengan pampas, lavender, dan furnitur rotan yang santai.',
                'desc_en' => 'Minimalist boho terrace with pampas, lavender, and relaxed rattan furniture.',
            ],
            [
                'name' => 'English Garden Romance Package',
                'category' => null, 'price' => 21000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Floral arch besar', 'Bunga mawar garden', 'Ivy & greenery', 'Pergola dekorasi', 'Aisle bunga segar'],
                'description' => 'Paket taman bunga Inggris yang romantis dengan floral arch besar, mawar garden, dan pergola yang dihiasi ivy.',
                'name_id' => 'Paket Romantis Taman Inggris', 'name_en' => 'English Garden Romance Package',
                'desc_id' => 'Paket taman bunga Inggris yang romantis dengan floral arch besar, mawar garden, dan pergola yang dihiasi ivy.',
                'desc_en' => 'Romantic English garden package with a large floral arch, garden roses, and an ivy-adorned pergola.',
            ],
            [
                'name' => 'Secret Garden Enchanted Package',
                'category' => null, 'price' => 23000000, 'discount_pct' => 8, 'stock' => 4,
                'features' => ['Secret garden gate', 'Bunga fairy rose', 'Moss & fern decor', 'Fairy lights canopy', 'Stone garden path'],
                'description' => 'Taman rahasia yang mempesona dengan fairy rose, moss, dan fairy lights canopy yang magis.',
                'name_id' => 'Paket Taman Rahasia Mempesona', 'name_en' => 'Secret Garden Enchanted Package',
                'desc_id' => 'Taman rahasia yang mempesona dengan fairy rose, moss, dan fairy lights canopy yang magis.',
                'desc_en' => 'Enchanted secret garden with fairy roses, moss, and a magical fairy lights canopy.',
            ],
            [
                'name' => 'Rose Garden Paradise Package',
                'category' => null, 'price' => 25000000, 'discount_pct' => 7, 'stock' => 4,
                'features' => ['Taman mawar ribuan', 'Bunga mawar rainbow', 'Gazebo putih', 'Aisle kelopak mawar', 'Air mancur'],
                'description' => 'Surga taman mawar dengan ribuan mawar rainbow, gazebo putih, dan air mancur yang menenangkan.',
                'name_id' => 'Paket Surga Taman Mawar', 'name_en' => 'Rose Garden Paradise Package',
                'desc_id' => 'Surga taman mawar dengan ribuan mawar rainbow, gazebo putih, dan air mancur yang menenangkan.',
                'desc_en' => 'Rose garden paradise with thousands of rainbow roses, white gazebo, and a calming fountain.',
            ],
            [
                'name' => 'Tropical Garden Oasis Package',
                'category' => null, 'price' => 19500000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Tropical foliage', 'Bunga hibiscus & frangipani', 'Bamboo pergola', 'Koi pond decor', 'Thatched roof'],
                'description' => 'Oase taman tropis dengan foliage lebat, bunga kembang sepatu, dan pergola bambu yang eksotis.',
                'name_id' => 'Paket Oase Taman Tropis', 'name_en' => 'Tropical Garden Oasis Package',
                'desc_id' => 'Oase taman tropis dengan foliage lebat, bunga kembang sepatu, dan pergola bambu yang eksotis.',
                'desc_en' => 'Tropical garden oasis with lush foliage, hibiscus flowers, and exotic bamboo pergola.',
            ],
            [
                'name' => 'Lavender Sunset Garden Package',
                'category' => null, 'price' => 17500000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Lavender garden', 'Bunga lavender segar', 'Purple draping', 'Twilight lighting', 'Provencal decor'],
                'description' => 'Taman lavender senja yang romantis dengan nuansa ungu dan lighting senja yang hangat.',
                'name_id' => 'Paket Taman Lavender Senja', 'name_en' => 'Lavender Sunset Garden Package',
                'desc_id' => 'Taman lavender senja yang romantis dengan nuansa ungu dan lighting senja yang hangat.',
                'desc_en' => 'Romantic lavender sunset garden with purple hues and warm twilight lighting.',
            ],
            [
                'name' => 'Grand Royal Ballroom Package',
                'category' => null, 'price' => 45000000, 'discount_pct' => 7, 'stock' => 3,
                'features' => ['Pelaminan emas mewah', 'Chandelier grand', 'Bunga premium import', 'Red carpet VIP', 'Dekorasi ceiling penuh', 'Tim 20 orang'],
                'description' => 'Paket kemewahan ballroom kerajaan dengan pelaminan emas, chandelier grand, dan bunga premium import.',
                'name_id' => 'Paket Ballroom Kerajaan Grand', 'name_en' => 'Grand Royal Ballroom Package',
                'desc_id' => 'Paket kemewahan ballroom kerajaan dengan pelaminan emas, chandelier grand, dan bunga premium import.',
                'desc_en' => 'Royal ballroom luxury package with golden throne, grand chandeliers, and imported premium flowers.',
            ],
            [
                'name' => 'Versailles Gold Elegance Package',
                'category' => null, 'price' => 50000000, 'discount_pct' => 8, 'stock' => 2,
                'features' => ['Konsep istana Versailles', 'Ornamen emas 24k', 'Bunga mawar merah premium', 'Candelabra set', 'Ceiling draping mewah', 'Lighting show'],
                'description' => 'Terinspirasi kemewahan Istana Versailles dengan ornamen emas, mawar merah premium, dan ceiling draping yang dramatis.',
                'name_id' => 'Paket Keanggunan Emas Versailles', 'name_en' => 'Versailles Gold Elegance Package',
                'desc_id' => 'Terinspirasi kemewahan Istana Versailles dengan ornamen emas, mawar merah premium, dan ceiling draping yang dramatis.',
                'desc_en' => 'Inspired by the luxury of Versailles Palace with gold ornaments, premium red roses, and dramatic ceiling draping.',
            ],
            [
                'name' => 'Imperial Palace Majestic Package',
                'category' => null, 'price' => 55000000, 'discount_pct' => 6, 'stock' => 2,
                'features' => ['Konsep istana China', 'Ornamen naga emas', 'Bunga peony merah', 'Lantern Chinese red', 'Silk draping premium'],
                'description' => 'Kemegahan istana kekaisaran China dengan nuansa merah emas, naga ornamen, dan peony merah.',
                'name_id' => 'Paket Megah Istana Kekaisaran', 'name_en' => 'Imperial Palace Majestic Package',
                'desc_id' => 'Kemegahan istana kekaisaran China dengan nuansa merah emas, naga ornamen, dan peony merah.',
                'desc_en' => 'Majestic Chinese imperial palace with red-gold hues, dragon ornaments, and red peonies.',
            ],
            [
                'name' => 'Arabian Nights Luxe Package',
                'category' => null, 'price' => 48000000, 'discount_pct' => 7, 'stock' => 2,
                'features' => ['Konsep Arabian', 'Gold arabesque decor', 'Bunga gardenia & jasmine', 'Silk tent draping', 'Crystal chandelier', 'Hookah corner'],
                'description' => 'Kemewahan seribu satu malam Arab dengan arabesque emas, silk tent, dan chandelier kristal.',
                'name_id' => 'Paket Mewah Arabian Nights', 'name_en' => 'Arabian Nights Luxe Package',
                'desc_id' => 'Kemewahan seribu satu malam Arab dengan arabesque emas, silk tent, dan chandelier kristal.',
                'desc_en' => 'Arabian Nights luxury with golden arabesque, silk tent, and crystal chandeliers.',
            ],
            [
                'name' => 'Baroque Golden Era Package',
                'category' => null, 'price' => 42000000, 'discount_pct' => 8, 'stock' => 3,
                'features' => ['Konsep Baroque Eropa', 'Gold leaf ornament', 'Bunga burgundy rose', 'Velvet draping', 'Candelabra antique', 'Classic painting backdrop'],
                'description' => 'Era keemasan Baroque Eropa dengan gold leaf, burgundy rose, dan velvet draping yang mewah.',
                'name_id' => 'Paket Era Emas Baroque', 'name_en' => 'Baroque Golden Era Package',
                'desc_id' => 'Era keemasan Baroque Eropa dengan gold leaf, burgundy rose, dan velvet draping yang mewah.',
                'desc_en' => 'European golden Baroque era with gold leaf, burgundy roses, and luxurious velvet draping.',
            ],
            [
                'name' => 'Tropical Beachfront Bliss Package',
                'category' => null, 'price' => 22000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Beachfront altar', 'Bunga frangipani', 'Shell & starfish decor', 'White draping billow', 'Coconut lantern'],
                'description' => 'Pernikahan di tepi pantai tropis dengan altar menghadap laut, frangipani, dan dekorasi kerang.',
                'name_id' => 'Paket Bahagia Tepi Pantai Tropis', 'name_en' => 'Tropical Beachfront Bliss Package',
                'desc_id' => 'Pernikahan di tepi pantai tropis dengan altar menghadap laut, frangipani, dan dekorasi kerang.',
                'desc_en' => 'Tropical beachfront wedding with ocean-facing altar, frangipani, and shell decorations.',
            ],
            [
                'name' => 'Sunset Cliff Romance Package',
                'category' => null, 'price' => 28000000, 'discount_pct' => 7, 'stock' => 3,
                'features' => ['Cliffside altar', 'Bunga orange lily', 'Tiki torch lighting', 'Rattan aisle decor', 'Sunset photo spot'],
                'description' => 'Pernikahan romantis di tebing karang dengan pemandangan sunset yang spektakuler.',
                'name_id' => 'Paket Romantis Tebing Senja', 'name_en' => 'Sunset Cliff Romance Package',
                'desc_id' => 'Pernikahan romantis di tebing karang dengan pemandangan sunset yang spektakuler.',
                'desc_en' => 'Romantic cliffside wedding with spectacular sunset views.',
            ],
            [
                'name' => 'Nautical Coastal Charm Package',
                'category' => null, 'price' => 19000000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Konsep nautical', 'Bunga blue hydrangea', 'Rope & anchor decor', 'Wooden barrel table', 'Striped blue white'],
                'description' => 'Pesona pesisir dengan tema nautical biru putih, hydrangea, dan dekorasi tali kapal.',
                'name_id' => 'Paket Pesona Pesisir Nautical', 'name_en' => 'Nautical Coastal Charm Package',
                'desc_id' => 'Pesona pesisir dengan tema nautical biru putih, hydrangea, dan dekorasi tali kapal.',
                'desc_en' => 'Coastal charm with blue-white nautical theme, hydrangeas, and ship rope decorations.',
            ],
            [
                'name' => 'Island Paradise Escape Package',
                'category' => null, 'price' => 25000000, 'discount_pct' => 8, 'stock' => 4,
                'features' => ['Pulau pribadi konsep', 'Bunga tropical mix', 'Bamboo gazebo', 'White sand aisle', 'Coconut bar'],
                'description' => 'Escape ke pulau pribadi dengan gazebo bambu, bunga tropis, dan aisle pasir putih.',
                'name_id' => 'Paket Pelarian Pulau Surga', 'name_en' => 'Island Paradise Escape Package',
                'desc_id' => 'Escape ke pulau pribadi dengan gazebo bambu, bunga tropis, dan aisle pasir putih.',
                'desc_en' => 'Private island escape with bamboo gazebo, tropical flowers, and white sand aisle.',
            ],
            [
                'name' => 'Seashore Breeze Package',
                'category' => null, 'price' => 16000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Seaside simple altar', 'Bunga baby breath', 'Seaglass decor', 'Muslin draping', 'Driftwood accent'],
                'description' => 'Pernikahan pantai sederhana dengan baby breath, seaglass, dan driftwood yang natural.',
                'name_id' => 'Paket Angin Laut Segar', 'name_en' => 'Seashore Breeze Package',
                'desc_id' => 'Pernikahan pantai sederhana dengan baby breath, seaglass, dan driftwood yang natural.',
                'desc_en' => 'Simple seaside wedding with baby breath, seaglass, and natural driftwood.',
            ],
            [
                'name' => 'Vintage Parisian Garden Package',
                'category' => null, 'price' => 20000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Konsep Parisian', 'Bunga lavender & rose', 'Vintage furniture', 'Eiffel tower mini', 'Pastel color palette'],
                'description' => 'Taman Paris vintage yang romantis dengan furnitur vintage, lavender, dan palet pastel.',
                'name_id' => 'Paket Taman Paris Vintage', 'name_en' => 'Vintage Parisian Garden Package',
                'desc_id' => 'Taman Paris vintage yang romantis dengan furnitur vintage, lavender, dan palet pastel.',
                'desc_en' => 'Romantic vintage Parisian garden with vintage furniture, lavender, and pastel palette.',
            ],
            [
                'name' => 'Retro Classic Love Package',
                'category' => null, 'price' => 16000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Retro 50s style', 'Bunga carnation mix', 'Vintage car decor', 'Record player', 'Checkerboard floor'],
                'description' => 'Gaya retro 50-an yang ceria dengan dekorasi mobil vintage, record player, dan checkerboard.',
                'name_id' => 'Paket Cinta Retro Klasik', 'name_en' => 'Retro Classic Love Package',
                'desc_id' => 'Gaya retro 50-an yang ceria dengan dekorasi mobil vintage, record player, dan checkerboard.',
                'desc_en' => 'Cheerful 50s retro style with vintage car decor, record player, and checkerboard.',
            ],
            [
                'name' => 'Antique Lace Romance Package',
                'category' => null, 'price' => 18000000, 'discount_pct' => 9, 'stock' => 4,
                'features' => ['Lace premium decor', 'Bunga dusty miller', 'Pearl ornament', 'Antique mirror', 'Candle vintage'],
                'description' => 'Romansa renda antik dengan lace premium, dusty miller, dan ornamen mutiara yang elegan.',
                'name_id' => 'Paket Romansa Renda Antik', 'name_en' => 'Antique Lace Romance Package',
                'desc_id' => 'Romansa renda antik dengan lace premium, dusty miller, dan ornamen mutiara yang elegan.',
                'desc_en' => 'Antique lace romance with premium lace, dusty miller, and elegant pearl ornaments.',
            ],
            [
                'name' => 'Old Hollywood Glamour Package',
                'category' => null, 'price' => 27000000, 'discount_pct' => 7, 'stock' => 3,
                'features' => ['Konsep Hollywood glam', 'Bunga white gardenia', 'Gold mirror decor', 'Velvet red curtain', 'Marquee light sign'],
                'description' => 'Kemewahan Old Hollywood dengan gardenia putih, velvet curtain, dan marquee light sign.',
                'name_id' => 'Paket Glamour Hollywood Klasik', 'name_en' => 'Old Hollywood Glamour Package',
                'desc_id' => 'Kemewahan Old Hollywood dengan gardenia putih, velvet curtain, dan marquee light sign.',
                'desc_en' => 'Old Hollywood glamour with white gardenias, velvet curtains, and marquee light signs.',
            ],
            [
                'name' => 'Victorian Rose Elegance Package',
                'category' => null, 'price' => 23000000, 'discount_pct' => 8, 'stock' => 4,
                'features' => ['Konsep Victorian', 'Bunga burgundy rose', 'Chandelier vintage', 'Wallpaper floral', 'Tea set antique'],
                'description' => 'Keanggunan era Victorian dengan burgundy rose, chandelier vintage, dan wallpaper floral.',
                'name_id' => 'Paket Keanggunan Mawar Victorian', 'name_en' => 'Victorian Rose Elegance Package',
                'desc_id' => 'Keanggunan era Victorian dengan burgundy rose, chandelier vintage, dan wallpaper floral.',
                'desc_en' => 'Victorian era elegance with burgundy roses, vintage chandelier, and floral wallpaper.',
            ],
            [
                'name' => 'Industrial Loft Chic Package',
                'category' => null, 'price' => 20000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Brick wall exposed', 'Bunga protea mix', 'Metal pipe decor', 'Edison bulb lighting', 'Concrete floor'],
                'description' => 'Loft industrial chic dengan brick wall exposed, protea, dan edison bulb lighting.',
                'name_id' => 'Paket Industrial Loft Chic', 'name_en' => 'Industrial Loft Chic Package',
                'desc_id' => 'Loft industrial chic dengan brick wall exposed, protea, dan edison bulb lighting.',
                'desc_en' => 'Industrial loft chic with exposed brick walls, proteas, and edison bulb lighting.',
            ],
            [
                'name' => 'Brick Wall Urban Package',
                'category' => null, 'price' => 17000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Brick wall gallery', 'Bunga sunflower wild', 'Steel frame decor', 'Pallet wood table', 'Street art mural'],
                'description' => 'Urban brick wall gallery dengan sunflower, steel frame, dan street art yang edgy.',
                'name_id' => 'Paket Urban Brick Wall', 'name_en' => 'Brick Wall Urban Package',
                'desc_id' => 'Urban brick wall gallery dengan sunflower, steel frame, dan street art yang edgy.',
                'desc_en' => 'Urban brick wall gallery with sunflowers, steel frames, and edgy street art.',
            ],
            [
                'name' => 'Warehouse Raw Beauty Package',
                'category' => null, 'price' => 18500000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Raw warehouse concept', 'Bunga eucalyptus & thistle', 'Metal grid decor', 'Crate wood table', 'Hanging vines'],
                'description' => 'Keindahan mentah warehouse dengan eucalyptus, metal grid, dan hanging vines.',
                'name_id' => 'Paket Keindahan Mentah Warehouse', 'name_en' => 'Warehouse Raw Beauty Package',
                'desc_id' => 'Keindahan mentah warehouse dengan eucalyptus, metal grid, dan hanging vines.',
                'desc_en' => 'Raw warehouse beauty with eucalyptus, metal grids, and hanging vines.',
            ],
            [
                'name' => 'Modern Factory Exposed Package',
                'category' => null, 'price' => 22000000, 'discount_pct' => 7, 'stock' => 4,
                'features' => ['Factory exposed ceiling', 'Bunga anthurium exotic', 'Chain & pulley decor', 'Catwalk stage', 'Industrial fan'],
                'description' => 'Factory modern dengan exposed ceiling, anthurium eksotis, dan catwalk stage yang dramatis.',
                'name_id' => 'Paket Pabrik Modern Ekspos', 'name_en' => 'Modern Factory Exposed Package',
                'desc_id' => 'Factory modern dengan exposed ceiling, anthurium eksotis, dan catwalk stage yang dramatis.',
                'desc_en' => 'Modern factory with exposed ceiling, exotic anthuriums, and dramatic catwalk stage.',
            ],
            [
                'name' => 'Steel and Glass Minimal Package',
                'category' => null, 'price' => 24000000, 'discount_pct' => 8, 'stock' => 4,
                'features' => ['Steel glass structure', 'Bunga succulent mix', 'Geometric steel decor', 'Glass partition', 'Green plant wall'],
                'description' => 'Struktur baja dan kaca minimalis dengan succulent, geometric steel, dan green plant wall.',
                'name_id' => 'Paket Baja dan Kaca Minimal', 'name_en' => 'Steel and Glass Minimal Package',
                'desc_id' => 'Struktur baja dan kaca minimalis dengan succulent, geometric steel, dan green plant wall.',
                'desc_en' => 'Minimalist steel and glass structure with succulents, geometric steel, and green plant wall.',
            ],
            [
                'name' => 'Tropical Paradise Garden Package',
                'category' => null, 'price' => 21000000, 'discount_pct' => 8, 'stock' => 5,
                'features' => ['Tropical garden lush', 'Bunga bird of paradise', 'Monstera leaves decor', 'Bali stone carving', 'Waterfall backdrop'],
                'description' => 'Taman tropis yang rimbun dengan bird of paradise, monstera, dan backdrop air terjun.',
                'name_id' => 'Paket Taman Surga Tropis', 'name_en' => 'Tropical Paradise Garden Package',
                'desc_id' => 'Taman tropis yang rimbun dengan bird of paradise, monstera, dan backdrop air terjun.',
                'desc_en' => 'Lush tropical garden with bird of paradise, monstera, and waterfall backdrop.',
            ],
            [
                'name' => 'Balinese Resort Style Package',
                'category' => null, 'price' => 25000000, 'discount_pct' => 7, 'stock' => 4,
                'features' => ['Konsep resort Bali', 'Bunga frangipani & lotus', 'Bale bangung pavilion', 'Candi batu', 'Kain tenun decor', 'Lotus pond'],
                'description' => 'Resort Bali yang mewah dengan frangipani, bale bangung pavilion, dan kolam lotus.',
                'name_id' => 'Paket Gaya Resort Bali', 'name_en' => 'Balinese Resort Style Package',
                'desc_id' => 'Resort Bali yang mewah dengan frangipani, bale bangung pavilion, dan kolam lotus.',
                'desc_en' => 'Luxurious Balinese resort with frangipani, bale bangung pavilion, and lotus pond.',
            ],
            [
                'name' => 'Exotic Rainforest Theme Package',
                'category' => null, 'price' => 23000000, 'discount_pct' => 8, 'stock' => 4,
                'features' => ['Rainforest concept', 'Bunga orchid exotic', 'Fern & moss wall', 'Wooden rope bridge', 'Jungle animal decor'],
                'description' => 'Hutan hujan tropis yang eksotis dengan orchid, fern, dan jembatan tali kayu.',
                'name_id' => 'Paket Hutan Hujan Eksotis', 'name_en' => 'Exotic Rainforest Theme Package',
                'desc_id' => 'Hutan hujan tropis yang eksotis dengan orchid, fern, dan jembatan tali kayu.',
                'desc_en' => 'Exotic rainforest with orchids, ferns, and wooden rope bridge.',
            ],
            [
                'name' => 'Island Tropical Flower Package',
                'category' => null, 'price' => 18000000, 'discount_pct' => 10, 'stock' => 5,
                'features' => ['Tropical flower mix', 'Bunga heliconia & ginger', 'Palm leaf decor', 'Coconut centerpiece', 'Hibiscus garland'],
                'description' => 'Ledakan warna bunga tropis dengan heliconia, ginger, dan palm leaf yang ceria.',
                'name_id' => 'Paket Bunga Tropis Pulau', 'name_en' => 'Island Tropical Flower Package',
                'desc_id' => 'Ledakan warna bunga tropis dengan heliconia, ginger, dan palm leaf yang ceria.',
                'desc_en' => 'Explosion of tropical flower colors with heliconia, ginger, and cheerful palm leaves.',
            ],
            [
                'name' => 'Jungle Green Wonder Package',
                'category' => null, 'price' => 19500000, 'discount_pct' => 9, 'stock' => 5,
                'features' => ['Jungle green decor', 'Bunga anthurium red', 'Philodendron wall', 'Tree trunk altar', 'Hanging fern garden'],
                'description' => 'Keajaiban hutan hijau dengan anthurium merah, philodendron wall, dan altar batang pohon.',
                'name_id' => 'Paket Keajaiban Hijau Hutan', 'name_en' => 'Jungle Green Wonder Package',
                'desc_id' => 'Keajaiban hutan hijau dengan anthurium merah, philodendron wall, dan altar batang pohon.',
                'desc_en' => 'Green jungle wonder with red anthuriums, philodendron wall, and tree trunk altar.',
            ],
        ];

        $discountIndexes = collect(range(0, count($catalogs) - 1))->shuffle()->take(25)->all();
        $totalCatalogs = count($catalogs);

        // Sumber foto WAJIB di luar folder storage media.
        //
        // Kenapa dicek, bukan sekadar satu komentar: `public/images/catalog`
        // pernah terisi penuh lalu TERHAPUS seluruhnya hanya karena seeder ini
        // dijalankan. Penyebabnya `clearMediaCollection()` di bawah menghapus
        // file media, dan kalau folder sumber berada di dalam tree yang sama --
        // atau adalah junction/symlink ke sana -- file sumber ikut terhapus.
        // Setelah itu seeder kedua langsung menyalin apa adanya: katalog 1 dapat
        // 1 gambar, katalog 2 dapat 1, katalog 100 dapat 0. Grid katalog jadi
        // kosong tanpa error sama sekali.
        //
        // `realpath()` dipakai supaya junction ikut ter-resolve: dua path yang
        //beda di teks bisa menunjuk file yang sama.
        $sourceDir = $this->resolveSourceImageDir();
        $storageDir = realpath(storage_path('app/public'));

        if ($sourceDir !== false && $storageDir !== false && str_starts_with($sourceDir, $storageDir)) {
            throw new RuntimeException(sprintf(
                'Folder gambar katalog (%s) berada DI DALAM folder storage media (%s). '
                .'Sephere::clearMediaCollection() akan menghapus file sumber. '
                .'Pindahkan gambar sumber ke luar storage, mis. database_path("seeders/catalog-images").',
                $sourceDir,
                $storageDir,
            ));
        }

        $missingSources = [];

        // Folder kerja untuk file sementara. Dibuat sekali, dipakai ulang
        // seluruh katalog, lalu dibersihkan di finally -- bukan per file,
        // supaya tidak ada ribuan pembuatan folder.
        $stagingDir = storage_path('framework/cache/catalog-seed-staging');

        if (! is_dir($stagingDir) && ! @mkdir($stagingDir, 0777, true) && ! is_dir($stagingDir)) {
            throw new RuntimeException("Tidak bisa membuat folder staging: {$stagingDir}");
        }

        $this->command->info('Folder gambar sumber : '.$sourceDir);
        $this->command->info('Folder staging       : '.$stagingDir);

        try {
            $this->seedCatalogMedia($catalogs, $cats, $discountIndexes, $sourceDir, $stagingDir, $missingSources, $totalCatalogs);
        } finally {
            // Hanya file milik proses ini yang dihapus.
            foreach ((array) @glob($stagingDir.'/seed-'.getmypid().'-*.png') as $leftover) {
                @unlink($leftover);
            }
        }

        // Terapkan 5 paket pada katalog yang baru saja dibuat.
        //
        // Dipindahkan dari PackageSeeder ke sini karena paket adalah TIER milik
        // katalog: ia tidak punya halaman, tidak bisa dicari, dan tidak berdiri
        // sendiri. Satu katalog tanpa paket adalah katalog yang belum lengkap,
        // jadi keduanya sebaiknya selesai dalam satu kali jalan seed.
        $this->seedPackageTiers($totalCatalogs);

        if ($missingSources) {
            sort($missingSources);
            $this->command->warn(sprintf(
                'Nomor gambar sumber yang tidak ditemukan: %s',
                implode(', ', array_unique($missingSources)),
            ));
        }

        $this->command->info('--- Catalog Seeding Complete ('.$totalCatalogs.' catalogs) ---');
    }

    /**
     * Menyimpan tiap katalog beserta 5 fotonya.
     *
     * Dipisah dari {@see static::run()} hanya supaya blok `try/finally`
     * pembersihan staging tidak membuat method run() terlalu dalam.
     *
     * @param  array<int, array<string, mixed>>  $catalogs
     * @param  \Illuminate\Support\Collection  $cats
     * @param  array<int, int>  $discountIndexes
     * @param  string  $sourceDir  Folder gambar sumber (pasti di luar storage media).
     * @param  string  $stagingDir  Folder kerja file sementara.
     * @param  array<int, int>  $missingSources  Diisi dengan nomor gambar yang tidak ditemukan.
     * @param  int  $totalCatalogs
     */
    private function seedCatalogMedia(
        array $catalogs,
        $cats,
        array $discountIndexes,
        string $sourceDir,
        string $stagingDir,
        array &$missingSources,
        int $totalCatalogs,
    ): void {
        foreach ($catalogs as $i => $data) {
            $catalogNumber = $i + 1;
            $slug = Str::slug($data['name']);
            $discountPct = in_array($i, $discountIndexes) ? (int) ($data['discount_pct'] ?? 0) : 0;
            $discountPrice = $discountPct > 0
                ? (int) round($data['price'] * (100 - $discountPct) / 100)
                : null;

            // Use direct translations without calling translation service (faster)
            $nameTranslations = [
                'id' => $data['name_id'],
                'en' => $data['name_en'],
            ];
            $descTranslations = [
                'id' => $data['desc_id'],
                'en' => $data['desc_en'],
            ];

            // CategorySeeder menormalkan slug dari nama terjemahan (contoh
            // `Lighting & Lampu` menjadi `lighting-lampu`), sementara data
            // katalog lama memakai slug pendek `lighting`. Cari exact match
            // lebih dulu lalu fallback ke slug kategori yang diawali key lama.
            $category = $data['category'] === null
                ? null
                : ($cats[$data['category']] ?? $cats->first(
                    fn ($candidate) => str($candidate->slug)->startsWith($data['category'].'-')
                ));

            $catalog = Catalog::updateOrCreate(
                ['slug' => $slug],
                [
                    // `category` null = katalog tanpa kategori.
                    //
                    // 50 item "Flower Decoration Package" sengaja dibiarkan
                    // tanpa kategori: paket bukan lagi halaman tersendiri, ia
                    // hanya tier di dalam halaman katalog, jadi tidak punya
                    // jenis produk yang perlu dikategorikan.
                    //
                    // DULU item-item ini memakai kategori level harga
                    // (hemat / standar / premium / mewah / ...). Dua masalah:
                    // kategori itu sudah dihapus dari CategorySeeder karena
                    // itu TIER, bukan produk -- sehingga `$cats[...]`
                    // mengembalikan null dan INSERT gagal dengan "Foreign key
                    // constraint fails". Dan kalau kategori paket dibiarkan,
                    // admin akan ditawari "Paket Royal" sebagai jenis dekorasi.
                    'category_id' => $category?->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'name_translations' => $nameTranslations,
                    'description_translations' => $descTranslations,
                    'price' => $data['price'],
                    'discount_price' => $discountPrice,
                    'stock' => $data['stock'],
                    'is_featured' => isset($data['features']) ? true : false,
                    'features' => $data['features'] ?? null,
                    'is_active' => true,
                ]
            );

            // 5 foto per katalog, sliding window:
            //   Katalog 1 → 1-5, Katalog 2 → 2-6, ... Katalog 98 → 98-100 + 1-2, ...
            //
            // PENTING -- tiga detail yang dulu salah dan membuat grid katalog
            // kehilangan foto:
            //
            // 1. `$i` (indeks katalog) TIDAK boleh dipakai ulang sebagai
            //    penghitung loop gambar. `foreach ($catalogs as $i => $data)`
            //    lalu `for ($i = 0; ...)` menimpa $i yang sama, jadi
            //    $catalogNumber pada iterasi berikutnya ikut bergeser dan katalog
            //    bergeser sumber fotonya. Di bawah dipakai `$offset`.
            //
            // 2. Nama file harus unik per katalog. `preservingOriginal()`
            //    mempertahankan "catalog-N.png" apa adanya, sementara
            //    CollectionPathGenerator menaruh semua media datar di
            //    `storage/app/public/Catalogs/`. Dua katalog yang memakai sumber
            //    foto sama berarti DUA baris media menunjuk SATU file. Begitu
            //    satu katalog di-`clearMediaCollection()`, file itu ikut terhapus
            //    dan katalog Tetangga ikut kehilangan fotonya -- gejala kartu
            //    kosong tanpa error. Prefix `$catalogNumber` membuat tiap baris
            //    media punya file sendiri, sesuai kontrak yang dicatat di
            //    CollectionPathGenerator.
            //
            // 3. `addMedia()` MEMINDAHKAN file sumber, bukan menyalin. Semua
            //    detail lain di bawah berputar tanpa ini: sumber habis terpakai
            //    setelah katalog pertama, sisanya kosong.
            $totalImages = 100;
            $imagesPerCatalog = 5;
            $startImage = (($catalogNumber - 1) % $totalImages) + 1;

            // Hapus media lama SETELAH semua file sumber dipastikan ada.
            //
            // Urutan ini penting. Kalau `clearMediaCollection()` dipanggil lebih
            // dulu dan ternyata file sumber ternyata hilang, katalog ini sudah
            // kehilangan foto lamanya tanpa ada penggantinya -- gagal parsial yang
            // tidak ada error-nya. Dengan dicek dulu, katalog yang gagal hanya
            // jadi kosong, bukan ikut kehilangan.
            $sources = [];
            for ($offset = 0; $offset < $imagesPerCatalog; $offset++) {
                $imageNum = (($startImage - 1 + $offset) % $totalImages) + 1;
                $source = $sourceDir.'/catalog-'.$imageNum.'.png';

                if (! is_file($source)) {
                    $missingSources[] = $imageNum;

                    continue;
                }

                $sources[] = [$imageNum, $source];
            }

            if (count($sources) < $imagesPerCatalog) {
                $this->command->warn(sprintf(
                    '    ! katalog %d hanya punya %d/%d gambar sumber; media lama DIPERTAHANKAN.',
                    $catalogNumber,
                    count($sources),
                    $imagesPerCatalog,
                ));

                $this->command->line("  <info>✓</info> [{$catalogNumber}/{$totalCatalogs}] {$data['name']} (media lama dipertahankan)");

                continue;
            }

            $catalog->clearMediaCollection('catalog_image');

            $attached = 0;

            foreach ($sources as [$imageNum, $source]) {
                // WAJIB: salin ke file sementara dulu.
                //
                // `addMedia()` MELETAKKAN file sumber -- dia memindahkan file itu
                // ke folder media, bukan menyalin. Terverifikasi lewat uji
                // terkontrol: satu file uji di folder gambar katalog langsung
                // hilang setelah `addMedia()`.
                //
                // Efeknya ke seeder: katalog 1 memakai catalog-1..5, jadi begitu
                // kelima file itu diproses, sumbernya habis. Katalog berikutnya
                // yang butuh catalog-2..6 cuma menemukan satu file, katalog
                // berikutnya nol. Grid katalog jadi kosong tanpa error, dan
                // seed ulang justru menghapus foto yang masih ada.
                //
                // Filename sementara memakai getmypid() supaya dua proses seed
                // yang kebetulan jalan bersamaan tidak saling menimpa.
                $staged = $stagingDir.'/seed-'.getmypid().'-'.$imageNum.'.png';

                if (! @copy($source, $staged)) {
                    $this->command->warn("    ! gagal menyalin sumber catalog-{$imageNum}.png");

                    continue;
                }

                $catalog->addMedia($staged)
                    // Prefix id katalog => nama file unik per katalog, jadi
                    // setiap baris media punya file sendiri di folder datar.
                    ->usingFileName("{$catalogNumber}-catalog-{$imageNum}.png")
                    ->toMediaCollection('catalog_image');

                // addMedia() memindahkan file, jadi biasanya sudah tidak ada.
                // unlink() tetap dipanggil karena kalau konversi gagal atau
                // config berubah, file bisa saja tertinggal di staging.
                @unlink($staged);

                $attached++;
            }

            $this->command->line("  <info>✓</info> [{$catalogNumber}/{$totalCatalogs}] {$data['name']} ({$attached} gambar)");
        }

        if ($missingSources) {
            sort($missingSources);
            $this->command->warn(sprintf(
                'Gambar sumber yang dipakai lebih dari sekali tapi tidak ditemukan: %s',
                implode(', ', array_unique($missingSources)),
            ));
        }

        $this->command->info('--- Catalog Seeding Complete ('.$totalCatalogs.' catalogs) ---');
    }

    /**
     * Lima paket dekorasi yang menempel pada setiap katalog.
     *
     * Dulu ini hidup di PackageSeeder terpisah. Sekarang satu file karena
     * paket tidak berdiri sendiri: begitu katalog di-seed, kelimanya ikut.
     *
     * `features_id`/`features_en` sengaja TIDAK dipakai untuk disimpan apa adanya.
     * Nilai yang disimpan adalah `features_id` sebagai array of string -- bukan
     * array of object `[['id' => ..., 'en' => ...]]`.
     *
     * Alasannya: blade `catalog-tier-selector` meneruskan `features` langsung ke
     * Alpine, dan `x-text="feature"` untuk objek akan menghasilkan literal
     * "[object Object]" di halaman detail katalog. Itu bukan theoretis -- itu
     * persis yang muncul di layar. Sudah ada normalisasi di blade sebagai
     * jaring pengaman, tapi data disimpan dalam bentuk yang bisa langsung
     * dirender supaya tidak bergantung pada satu lapis saja.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function packageTiers(): array
    {
        return [
            [
                'tier_key' => 'standard',
                'sort_order' => 1,
                'name_id' => 'Paket Standar',
                'name_en' => 'Standard Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga standar dengan pilihan bunga segar dasar, backdrop sederhana, dan lighting standar. Cocok untuk pernikahan intim dengan budget terjangkau.',
                'desc_en' => 'Standard flower decoration package with basic fresh flowers, simple backdrop, and standard lighting. Perfect for intimate weddings with affordable budget.',
                'price' => 15000000,
                'discount_pct' => 10,
                'features_id' => [
                    'Bunga segar pilihan standar',
                    'Backdrop pelaminan sederhana',
                    'Lighting standar',
                    'Dekorasi meja resepsi',
                    'Tim setup 2 orang',
                ],
                'features_en' => [
                    'Standard fresh flower selection',
                    'Simple wedding backdrop',
                    'Standard lighting',
                    'Reception table decoration',
                    '2-person setup team',
                ],
                'color' => '#9CA3AF',
            ],
            [
                'tier_key' => 'medium',
                'sort_order' => 2,
                'name_id' => 'Paket Sedang',
                'name_en' => 'Medium Flower Decoration Package',
                'desc_id' => 'Paket dekorasi bunga tingkat sedang dengan variasi bunga lebih lengkap, backdrop custom, lighting upgraded, dan aksesoris dekorasi tambahan.',
                'desc_en' => 'Medium flower decoration package with more complete flower variations, custom backdrop, upgraded lighting, and additional decoration accessories.',
                'price' => 25000000,
                'discount_pct' => 12,
                'features_id' => [
                    'Bunga segar premium & filler',
                    'Backdrop custom design',
                    'Lighting upgraded (fairy light + spot)',
                    'Dekorasi meja & aisle lengkap',
                    'Aksesoris dekorasi (vas, candle, dll)',
                    'Tim setup 3 orang',
                ],
                'features_en' => [
                    'Premium fresh flowers & fillers',
                    'Custom design backdrop',
                    'Upgraded lighting (fairy light + spot)',
                    'Complete table & aisle decoration',
                    'Decoration accessories (vases, candles, etc)',
                    '3-person setup team',
                ],
                'color' => '#6366F1',
            ],
            [
                'tier_key' => 'ceremony',
                'sort_order' => 3,
                'name_id' => 'Paket Akad & Resepsi',
                'name_en' => 'Ceremony or Wedding Flower Decoration Package',
                'desc_id' => 'Paket lengkap untuk akad nikah dan resepsi dengan dekorasi bunga premium, double backdrop (akad & resepsi), full lighting, dan tim profesional.',
                'desc_en' => 'Complete package for wedding ceremony and reception with premium flower decoration, double backdrop (ceremony & reception), full lighting, and professional team.',
                'price' => 40000000,
                'discount_pct' => 10,
                'features_id' => [
                    'Double backdrop (akad & resepsi)',
                    'Bunga premium import & lokal',
                    'Full lighting design',
                    'Dekorasi aisle & altar lengkap',
                    'Flower wall / photo booth',
                    'Meja akad mewah + kursi pengantin',
                    'Tim setup 5 orang + koordinator',
                ],
                'features_en' => [
                    'Double backdrop (ceremony & reception)',
                    'Premium imported & local flowers',
                    'Full lighting design',
                    'Complete aisle & altar decoration',
                    'Flower wall / photo booth',
                    'Premium ceremony table + bride/groom chairs',
                    '5-person setup team + coordinator',
                ],
                'color' => '#EC4899',
            ],
            [
                'tier_key' => 'engagement',
                'sort_order' => 4,
                'name_id' => 'Paket Tunangan',
                'name_en' => 'Engagement Flower Decoration Package',
                'desc_id' => 'Paket khusus untuk acara tunangan/lamaran dengan dekorasi bunga romantis, backdrop elegan, lighting hangat, dan nuansa intim yang memikat.',
                'desc_en' => 'Special package for engagement ceremony with romantic flower decoration, elegant backdrop, warm lighting, and captivating intimate atmosphere.',
                'price' => 20000000,
                'discount_pct' => 15,
                'features_id' => [
                    'Backdrop tunangan elegan',
                    'Bunga romantis (mawar, peony, hydrangea)',
                    'Lighting hangat (warm tone)',
                    'Dekorasi meja ringan',
                    'Ring box & flower crown',
                    'Tim setup 3 orang',
                ],
                'features_en' => [
                    'Elegant engagement backdrop',
                    'Romantic flowers (roses, peonies, hydrangeas)',
                    'Warm tone lighting',
                    'Light table decoration',
                    'Ring box & flower crown',
                    '3-person setup team',
                ],
                'color' => '#F59E0B',
            ],
            [
                'tier_key' => 'full',
                'sort_order' => 5,
                'name_id' => 'Paket Lengkap (All-in-One)',
                'name_en' => 'Full Flower Decoration Package',
                'desc_id' => 'Paket all-in-one paling lengkap: akad + resepsi + tunangan + aksesoris lengkap + tim besar + manajemen penuh. Cocok untuk pernikahan megah tanpa ribet.',
                'desc_en' => 'Most complete all-in-one package: ceremony + reception + engagement + complete accessories + large team + full management. Perfect for grand hassle-free weddings.',
                'price' => 65000000,
                'discount_pct' => 8,
                'features_id' => [
                    'Triple backdrop (tunangan, akad, resepsi)',
                    'Bunga premium terpilih (import & lokal terbaik)',
                    'Full lighting & sound system',
                    'Dekorasi komprehensif (aisle, altar, meja, ceiling, dll)',
                    'Flower wall, photo booth, welcome sign',
                    'Meja akad mewah, kursi pengantin, sofa VIP',
                    'Aksesoris lengkap (vas, candle, lantern, draping, dll)',
                    'Tim setup 8-10 orang + project manager',
                    'Manajemen penuh hari-H (on-site coordination)',
                ],
                'features_en' => [
                    'Triple backdrop (engagement, ceremony, reception)',
                    'Hand-picked premium flowers (best import & local)',
                    'Full lighting & sound system',
                    'Comprehensive decoration (aisle, altar, tables, ceiling, etc)',
                    'Flower wall, photo booth, welcome sign',
                    'Premium ceremony table, bride/groom chairs, VIP sofa',
                    'Complete accessories (vases, candles, lanterns, draping, etc)',
                    '8-10 person setup team + project manager',
                    'Full day-of management (on-site coordination)',
                ],
                'color' => '#10B981',
            ],
        ];
    }

    /**
     * Terapkan lima paket ke setiap katalog aktif.
     *
     * Dijalankan dari run() setelah semua katalog dibuat, bukan per-katalog di
     * dalam loop. Alasannya `updateOrCreate()` pada relasi `tiers()` menulis
     * satu baris packages per panggilan; memanggilnya di dalam loop yang sama
     * dengan loop gambar menambah banyak query tanpa perlu.
     *
     * @param  int  $expectedCatalogs  Jumlah katalog yang baru di-seed, hanya untuk pesan ringkasan.
     */
    private function seedPackageTiers(int $expectedCatalogs): void
    {
        $this->command->info('--- Seeding Package Tiers (5 per catalog) ---');

        $catalogs = Catalog::where('is_active', true)->get();

        if ($catalogs->isEmpty()) {
            $this->command->warn('Tidak ada katalog. Seed paket dilewati.');

            return;
        }

        $tiers = static::packageTiers();
        $attached = 0;

        foreach ($catalogs as $catalog) {
            foreach ($tiers as $tier) {
                // Standard adalah harga dasar milik katalog ini, bukan satu
                // angka global untuk semua katalog. Harga katalog hanya
                // dipakai saat mengisi tier Standard; halaman publik/admin
                // tetap membaca harga dari package tier tersebut.
                $tierPrice = $tier['tier_key'] === 'standard'
                    ? (float) $catalog->price
                    : (float) $tier['price'];

                $discountPrice = $tier['tier_key'] === 'standard'
                    ? ($catalog->discount_price !== null ? (float) $catalog->discount_price : null)
                    : ($tier['discount_pct'] > 0
                        ? (int) round($tierPrice * (100 - $tier['discount_pct']) / 100)
                        : null);

                Package::updateOrCreate(
                    // Slug menyertakan tier_key supaya setiap katalog punya
                    // paket sendiri dengan nama file unik, dan slug-nya tetap
                    // sama dengan yang dipakai PackageSeeder sebelumnya.
                    ['slug' => Str::slug($catalog->slug.'-'.$tier['tier_key'])],
                    [
                        'catalog_id' => $catalog->id,
                        'category_id' => $catalog->category_id,
                        'sort_order' => $tier['sort_order'],
                        'name' => $tier['name_id'],
                        'description' => $tier['desc_id'],
                        'name_translations' => [
                            'id' => $tier['name_id'],
                            'en' => $tier['name_en'],
                        ],
                        'description_translations' => [
                            'id' => $tier['desc_id'],
                            'en' => $tier['desc_en'],
                        ],
                        'price' => $tierPrice,
                        'discount_price' => $discountPrice,
                        'stock' => 10,
                        'is_active' => true,
                        'is_featured' => $tier['tier_key'] === 'full',
                        // Array of STRING, bukan [['id' =>, 'en' =>]].
                        // Lihat catatan di packageTiers() soal "[object Object]".
                        'features' => $tier['features_id'],
                        'color' => $tier['color'],
                        'theme' => $tier['tier_key'],
                    ],
                );

                $attached++;
            }
        }

        $this->command->info("--- Package Tier Seeding Complete ({$attached} tiers for {$catalogs->count()} catalogs) ---");
    }

    /**
     * Folder gambar sumber katalog, sudah di-resolve ke path absolut.
     *
     * Urutan path: dulu `database_path('seeders/catalog-images')`, lalu
     * `public/images/catalog`. Yang pertama lebih 안전 karena dijamin di luar
     * tree storage media.
     *
     * @return string Path absolut. Caller wajib mengecek `is_dir()` -- nilai
     *                kembalian bukan bukti folder-nya ada.
     */
    private function resolveSourceImageDir(): string
    {
        foreach ([database_path('seeders/catalog-images'), public_path('images/catalog')] as $candidate) {
            if (is_dir($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return realpath(public_path('images/catalog')) ?: public_path('images/catalog');
    }
}
