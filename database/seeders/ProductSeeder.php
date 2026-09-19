<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Models\Product\Product;
use App\Models\Vendor\Vendor;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use TranslatesContent;

    public function run(): void
    {
        $this->command->info('--- Seeding Products ---');

        $cats = Category::where('type', 'product')->get()->keyBy('slug');
        $vendors = Vendor::all();

        $products = [
            [
                'name' => 'Gebyok Ukir Jati Premium',
                'category' => 'backdrop-pelaminan', 'price' => 15000000, 'discount_pct' => 10, 'stock' => 10, 'image' => 'product-1.png',
                'description' => 'Gebyok ukir kayu jati pilihan dengan motif batik klasik. Cocok sebagai backdrop pelaminan adat Jawa yang megah.',
                'name_id' => 'Gebyok Ukir Jati Premium', 'name_en' => 'Premium Carved Teak Gebyok',
                'desc_id' => 'Gebyok ukir kayu jati pilihan dengan motif batik klasik. Cocok sebagai backdrop pelaminan adat Jawa yang megah.',
                'desc_en' => 'Selected teak wood carved gebyok with classic batik motifs. Perfect as a magnificent Javanese traditional wedding backdrop.',
            ],
            [
                'name' => 'Pelaminan Adat Jawa Mewah',
                'category' => 'backdrop-pelaminan', 'price' => 12000000, 'discount_pct' => 12, 'stock' => 8, 'image' => 'product-2.png',
                'description' => 'Pelaminan adat Jawa lengkap dengan ukiran emas, kain songket, dan ornamen tradisional.',
                'name_id' => 'Pelaminan Adat Jawa Mewah', 'name_en' => 'Luxurious Javanese Traditional Throne',
                'desc_id' => 'Pelaminan adat Jawa lengkap dengan ukiran emas, kain songket, dan ornamen tradisional.',
                'desc_en' => 'Complete Javanese traditional throne with gold carvings, songket fabric, and traditional ornaments.',
            ],
            [
                'name' => 'Kain Batik Tulis Cendana',
                'category' => 'kain-dekorasi', 'price' => 3500000, 'discount_pct' => 15, 'stock' => 20, 'image' => 'product-3.png',
                'description' => 'Batik tulis motif cendana dengan pewarna alami. Kain premium untuk dekorasi pernikahan adat.',
                'name_id' => 'Kain Batik Tulis Cendana', 'name_en' => 'Handwritten Sandalwood Batik Fabric',
                'desc_id' => 'Batik tulis motif cendana dengan pewarna alami. Kain premium untuk dekorasi pernikahan adat.',
                'desc_en' => 'Hand-drawn batik with sandalwood motif using natural dyes. Premium fabric for traditional wedding decor.',
            ],
            [
                'name' => 'Seserahan Pernikahan Adat',
                'category' => 'seserahan', 'price' => 5500000, 'discount_pct' => 10, 'stock' => 15, 'image' => 'product-4.png',
                'description' => 'Set seserahan pernikahan adat lengkap 8 kotak dengan hiasan bunga dan kain batik.',
                'name_id' => 'Seserahan Pernikahan Adat', 'name_en' => 'Traditional Wedding Seserahan Set',
                'desc_id' => 'Set seserahan pernikahan adat lengkap 8 kotak dengan hiasan bunga dan kain batik.',
                'desc_en' => 'Complete traditional wedding seserahan set of 8 boxes with flower decorations and batik fabric.',
            ],
            [
                'name' => 'Backdrop Batik Mega Mendung',
                'category' => 'backdrop-pelaminan', 'price' => 4500000, 'discount_pct' => 12, 'stock' => 12, 'image' => 'product-5.png',
                'description' => 'Backdrop besar dengan motif batik mega mendung khas Cirebon. Cocok untuk pelaminan adat.',
                'name_id' => 'Backdrop Batik Mega Mendung', 'name_en' => 'Mega Mendung Batik Backdrop',
                'desc_id' => 'Backdrop besar dengan motif batik mega mendung khas Cirebon. Cocok untuk pelaminan adat.',
                'desc_en' => 'Large backdrop with Cirebon mega mendung batik motif. Perfect for traditional wedding thrones.',
            ],
            [
                'name' => 'Chandelier Kristal Modern',
                'category' => 'lighting', 'price' => 20000000, 'discount_pct' => 10, 'stock' => 8, 'image' => 'product-6.png',
                'description' => 'Chandelier kristal premium untuk dekorasi ballroom modern. Memantulkan cahaya indah di setiap sudut ruangan.',
                'name_id' => 'Chandelier Kristal Modern', 'name_en' => 'Modern Crystal Chandelier',
                'desc_id' => 'Chandelier kristal premium untuk dekorasi ballroom modern. Memantulkan cahaya indah di setiap sudut ruangan.',
                'desc_en' => 'Premium crystal chandelier for modern ballroom decoration. Reflects beautiful light in every corner.',
            ],
            [
                'name' => 'Neon Sign Custom Wedding',
                'category' => 'aksesoris', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 25, 'image' => 'product-7.png',
                'description' => 'Neon sign custom dengan nama pasangan atau quote favorit. Dekorasi sekaligus kenang-kenangan.',
                'name_id' => 'Neon Sign Custom Wedding', 'name_en' => 'Custom Wedding Neon Sign',
                'desc_id' => 'Neon sign custom dengan nama pasangan atau quote favorit. Dekorasi sekaligus kenang-kenangan.',
                'desc_en' => 'Custom neon sign with couple names or favorite quotes. Serves as both decoration and keepsake.',
            ],
            [
                'name' => 'LED Backdrop Interaktif',
                'category' => 'lighting', 'price' => 8000000, 'discount_pct' => 11, 'stock' => 10, 'image' => 'product-8.png',
                'description' => 'Backdrop LED interaktif dengan resolusi tinggi. Bisa menampilkan foto pasangan atau animasi.',
                'name_id' => 'LED Backdrop Interaktif', 'name_en' => 'Interactive LED Backdrop',
                'desc_id' => 'Backdrop LED interaktif dengan resolusi tinggi. Bisa menampilkan foto pasangan atau animasi.',
                'desc_en' => 'Interactive LED backdrop with high resolution. Can display couple photos or animations.',
            ],
            [
                'name' => 'Aisle Runner Premium Putih',
                'category' => 'kain-dekorasi', 'price' => 2500000, 'discount_pct' => 15, 'stock' => 30, 'image' => 'product-9.png',
                'description' => 'Aisle runner premium putih dengan finishing mewah. Cocok untuk ballroom dan hotel.',
                'name_id' => 'Aisle Runner Premium Putih', 'name_en' => 'Premium White Aisle Runner',
                'desc_id' => 'Aisle runner premium putih dengan finishing mewah. Cocok untuk ballroom dan hotel.',
                'desc_en' => 'Premium white aisle runner with luxurious finish. Perfect for ballrooms and hotels.',
            ],
            [
                'name' => 'Geometric Backdrop Gold',
                'category' => 'ornamen-dinding', 'price' => 4200000, 'discount_pct' => 13, 'stock' => 15, 'image' => 'product-10.png',
                'description' => 'Backdrop geometris dengan finishing gold. Desain modern dan elegan untuk foto utama.',
                'name_id' => 'Geometric Backdrop Gold', 'name_en' => 'Gold Geometric Backdrop',
                'desc_id' => 'Backdrop geometris dengan finishing gold. Desain modern dan elegan untuk foto utama.',
                'desc_en' => 'Geometric backdrop with gold finish. Modern and elegant design for main photos.',
            ],
            [
                'name' => 'Arch Kayu Rustic Natural',
                'category' => 'ornamen-dinding', 'price' => 8500000, 'discount_pct' => 12, 'stock' => 15, 'image' => 'product-11.png',
                'description' => 'Arch kayu alami dengan finishing natural, dihiasi pampas grass dan bunga liar segar.',
                'name_id' => 'Arch Kayu Rustic Natural', 'name_en' => 'Natural Rustic Wood Arch',
                'desc_id' => 'Arch kayu alami dengan finishing natural, dihiasi pampas grass dan bunga liar segar.',
                'desc_en' => 'Natural wood arch with natural finish, decorated with pampas grass and fresh wildflowers.',
            ],
            [
                'name' => 'Macrame Bohemian Backdrop',
                'category' => 'ornamen-dinding', 'price' => 4500000, 'discount_pct' => 16, 'stock' => 12, 'image' => 'product-12.png',
                'description' => 'Backdrop macrame handmade dengan sentuhan bohemian. Unik, artistik, dan penuh karakter.',
                'name_id' => 'Macrame Bohemian Backdrop', 'name_en' => 'Bohemian Macrame Backdrop',
                'desc_id' => 'Backdrop macrame handmade dengan sentuhan bohemian. Unik, artistik, dan penuh karakter.',
                'desc_en' => 'Handmade macrame backdrop with bohemian touch. Unique, artistic, and full of character.',
            ],
            [
                'name' => 'Pampas Grass Decoration Set',
                'category' => 'buket-bunga', 'price' => 1800000, 'discount_pct' => 18, 'stock' => 25, 'image' => 'product-13.png',
                'description' => 'Set dekorasi pampas grass kering natural. Cocok untuk konsep rustic bohemian.',
                'name_id' => 'Set Dekorasi Pampas Grass', 'name_en' => 'Pampas Grass Decoration Set',
                'desc_id' => 'Set dekorasi pampas grass kering natural. Cocok untuk konsep rustic bohemian.',
                'desc_en' => 'Natural dried pampas grass decoration set. Perfect for rustic bohemian concepts.',
            ],
            [
                'name' => 'Fairy Light Curtain Warm',
                'category' => 'lighting', 'price' => 1200000, 'discount_pct' => 20, 'stock' => 40, 'image' => 'product-14.png',
                'description' => 'Tirai fairy light warm white 3x3 meter. Menciptakan suasana hangat dan romantis.',
                'name_id' => 'Tirai Fairy Light Warm', 'name_en' => 'Warm Fairy Light Curtain',
                'desc_id' => 'Tirai fairy light warm white 3x3 meter. Menciptakan suasana hangat dan romantis.',
                'desc_en' => 'Warm white fairy light curtain 3x3 meters. Creates a warm and romantic atmosphere.',
            ],
            [
                'name' => 'Rustic Wooden Table Set',
                'category' => 'dekorasi-meja', 'price' => 3800000, 'discount_pct' => 14, 'stock' => 10, 'image' => 'product-15.png',
                'description' => 'Set meja kayu rustic dengan finishing natural. Cocok untuk resepsi outdoor.',
                'name_id' => 'Set Meja Kayu Rustic', 'name_en' => 'Rustic Wooden Table Set',
                'desc_id' => 'Set meja kayu rustic dengan finishing natural. Cocok untuk resepsi outdoor.',
                'desc_en' => 'Rustic wooden table set with natural finish. Perfect for outdoor receptions.',
            ],
            [
                'name' => 'Flower Wall Blush Rose',
                'category' => 'ornamen-dinding', 'price' => 6500000, 'discount_pct' => 11, 'stock' => 20, 'image' => 'product-16.png',
                'description' => 'Dinding bunga mawar blush pink yang memukau. Menjadi spot foto favorit tamu undangan.',
                'name_id' => 'Flower Wall Blush Rose', 'name_en' => 'Blush Rose Flower Wall',
                'desc_id' => 'Dinding bunga mawar blush pink yang memukau. Menjadi spot foto favorit tamu undangan.',
                'desc_en' => 'Stunning blush pink rose flower wall. Becomes guests favorite photo spot.',
            ],
            [
                'name' => 'Greenery Wall Skandinavia',
                'category' => 'ornamen-dinding', 'price' => 5500000, 'discount_pct' => 9, 'stock' => 18, 'image' => 'product-17.png',
                'description' => 'Dinding hijau segar bergaya Skandinavia dengan tanaman pilihan. Bersih, natural, dan timeless.',
                'name_id' => 'Greenery Wall Skandinavia', 'name_en' => 'Scandinavian Greenery Wall',
                'desc_id' => 'Dinding hijau segar bergaya Skandinavia dengan tanaman pilihan. Bersih, natural, dan timeless.',
                'desc_en' => 'Fresh green wall in Scandinavian style with selected plants. Clean, natural, and timeless.',
            ],
            [
                'name' => 'Marble Ceremony Table',
                'category' => 'dekorasi-meja', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 12, 'image' => 'product-18.png',
                'description' => 'Meja akad nikah marble putih dengan kaki gold minimalis. Elegan dan fotogenik.',
                'name_id' => 'Meja Akad Marble Putih', 'name_en' => 'White Marble Ceremony Table',
                'desc_id' => 'Meja akad nikah marble putih dengan kaki gold minimalis. Elegan dan fotogenik.',
                'desc_en' => 'White marble wedding ceremony table with minimalist gold legs. Elegant and photogenic.',
            ],
            [
                'name' => 'Eucalyptus Garland Set',
                'category' => 'buket-bunga', 'price' => 1500000, 'discount_pct' => 18, 'stock' => 30, 'image' => 'product-19.png',
                'description' => 'Set garland eucalyptus segar untuk dekorasi meja dan arch. Aroma segar dan tampilan natural.',
                'name_id' => 'Set Garland Eucalyptus', 'name_en' => 'Eucalyptus Garland Set',
                'desc_id' => 'Set garland eucalyptus segar untuk dekorasi meja dan arch. Aroma segar dan tampilan natural.',
                'desc_en' => 'Fresh eucalyptus garland set for table and arch decoration. Fresh scent and natural look.',
            ],
            [
                'name' => 'Acrylic Sign Holder Gold',
                'category' => 'aksesoris', 'price' => 850000, 'discount_pct' => 20, 'stock' => 40, 'image' => 'product-20.png',
                'description' => 'Sign holder akrilik dengan kaki gold. Untuk menu, welcome sign, atau table numbers.',
                'name_id' => 'Akrilik Sign Holder Gold', 'name_en' => 'Gold Acrylic Sign Holder',
                'desc_id' => 'Sign holder akrilik dengan kaki gold. Untuk menu, welcome sign, atau table numbers.',
                'desc_en' => 'Acrylic sign holder with gold legs. For menus, welcome signs, or table numbers.',
            ],
            [
                'name' => 'Pergola Taman Inggris',
                'category' => 'dekorasi-meja', 'price' => 12000000, 'discount_pct' => 10, 'stock' => 6, 'image' => 'product-21.png',
                'description' => 'Pergola bergaya taman Inggris dengan ivy dan mawar garden. Suasana romantis di outdoor.',
                'name_id' => 'Pergola Taman Inggris', 'name_en' => 'English Garden Pergola',
                'desc_id' => 'Pergola bergaya taman Inggris dengan ivy dan mawar garden. Suasana romantis di outdoor.',
                'desc_en' => 'English garden style pergola with ivy and garden roses. Romantic outdoor atmosphere.',
            ],
            [
                'name' => 'Floral Arch Large Garden',
                'category' => 'ornamen-dinding', 'price' => 7800000, 'discount_pct' => 12, 'stock' => 8, 'image' => 'product-22.png',
                'description' => 'Arch bunga besar untuk taman dengan kombinasi mawar, hydrangea, dan greenery.',
                'name_id' => 'Arch Bunga Besar Taman', 'name_en' => 'Large Garden Floral Arch',
                'desc_id' => 'Arch bunga besar untuk taman dengan kombinasi mawar, hydrangea, dan greenery.',
                'desc_en' => 'Large floral arch for garden with combination of roses, hydrangeas, and greenery.',
            ],
            [
                'name' => 'Gazebo Putih Klasik',
                'category' => 'dekorasi-meja', 'price' => 15000000, 'discount_pct' => 8, 'stock' => 4, 'image' => 'product-23.png',
                'description' => 'Gazebo putih klasik dengan ornamen besi tempa. Cocok sebagai lokasi akad outdoor.',
                'name_id' => 'Gazebo Putih Klasik', 'name_en' => 'Classic White Gazebo',
                'desc_id' => 'Gazebo putih klasik dengan ornamen besi tempa. Cocok sebagai lokasi akad outdoor.',
                'desc_en' => 'Classic white gazebo with wrought iron ornaments. Perfect for outdoor ceremony location.',
            ],
            [
                'name' => 'Ivy Greenery Wall Panel',
                'category' => 'ornamen-dinding', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 20, 'image' => 'product-24.png',
                'description' => 'Panel dinding ivy buatan 1x2 meter. Mudah dipasang untuk dekorasi taman vertikal.',
                'name_id' => 'Panel Dinding Ivy Hijau', 'name_en' => 'Ivy Greenery Wall Panel',
                'desc_id' => 'Panel dinding ivy buatan 1x2 meter. Mudah dipasang untuk dekorasi taman vertikal.',
                'desc_en' => 'Artificial ivy wall panel 1x2 meters. Easy to install for vertical garden decoration.',
            ],
            [
                'name' => 'Garden Candle Lantern Set',
                'category' => 'lighting', 'price' => 1600000, 'discount_pct' => 18, 'stock' => 25, 'image' => 'product-25.png',
                'description' => 'Set lampion lilin taman 6 pcs. Menciptakan suasana hangat di resepsi outdoor.',
                'name_id' => 'Set Lampion Lilin Taman', 'name_en' => 'Garden Candle Lantern Set',
                'desc_id' => 'Set lampion lilin taman 6 pcs. Menciptakan suasana hangat di resepsi outdoor.',
                'desc_en' => 'Set of 6 garden candle lanterns. Creates a warm atmosphere at outdoor receptions.',
            ],
            [
                'name' => 'Pelaminan Emas Royal',
                'category' => 'backdrop-pelaminan', 'price' => 35000000, 'discount_pct' => 9, 'stock' => 3, 'image' => 'product-26.png',
                'description' => 'Pelaminan mewah berlapis emas dengan ornamen kerajaan. Untuk pernikahan yang benar-benar berkesan.',
                'name_id' => 'Pelaminan Emas Royal', 'name_en' => 'Royal Gold Wedding Throne',
                'desc_id' => 'Pelaminan mewah berlapis emas dengan ornamen kerajaan. Untuk pernikahan yang benar-benar berkesan.',
                'desc_en' => 'Luxurious gold-plated wedding throne with royal ornaments. For a truly memorable wedding.',
            ],
            [
                'name' => 'Candelabra Set Mewah',
                'category' => 'aksesoris', 'price' => 9000000, 'discount_pct' => 11, 'stock' => 8, 'image' => 'product-27.png',
                'description' => 'Set candelabra emas mewah untuk dekorasi meja dan aisle. Kesan elegan dan dramatis.',
                'name_id' => 'Candelabra Set Mewah', 'name_en' => 'Luxury Candelabra Set',
                'desc_id' => 'Set candelabra emas mewah untuk dekorasi meja dan aisle. Kesan elegan dan dramatis.',
                'desc_en' => 'Luxurious gold candelabra set for table and aisle decoration. Elegant and dramatic.',
            ],
            [
                'name' => 'Ceiling Draping Velvet Royal',
                'category' => 'kain-dekorasi', 'price' => 6500000, 'discount_pct' => 12, 'stock' => 8, 'image' => 'product-28.png',
                'description' => 'Draping ceiling velvet merah premium dengan aksen gold. Untuk ballroom megah.',
                'name_id' => 'Ceiling Draping Velvet Royal', 'name_en' => 'Royal Velvet Ceiling Draping',
                'desc_id' => 'Draping ceiling velvet merah premium dengan aksen gold. Untuk ballroom megah.',
                'desc_en' => 'Premium red velvet ceiling draping with gold accents. For a magnificent ballroom.',
            ],
            [
                'name' => 'Red Carpet VIP Set',
                'category' => 'kain-dekorasi', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 15, 'image' => 'product-29.png',
                'description' => 'Set red carpet VIP dengan stanchion gold dan rope barrier. Untuk penyambutan tamu.',
                'name_id' => 'Set Red Carpet VIP', 'name_en' => 'VIP Red Carpet Set',
                'desc_id' => 'Set red carpet VIP dengan stanchion gold dan rope barrier. Untuk penyambutan tamu.',
                'desc_en' => 'VIP red carpet set with gold stanchions and rope barrier. For guest reception.',
            ],
            [
                'name' => 'Royal Gold Table Centerpiece',
                'category' => 'dekorasi-meja', 'price' => 2200000, 'discount_pct' => 16, 'stock' => 20, 'image' => 'product-30.png',
                'description' => 'Centerpiece meja emas royal dengan bunga mawar merah dan kristal.',
                'name_id' => 'Centerpiece Emas Royal', 'name_en' => 'Royal Gold Table Centerpiece',
                'desc_id' => 'Centerpiece meja emas royal dengan bunga mawar merah dan kristal.',
                'desc_en' => 'Royal gold table centerpiece with red roses and crystals.',
            ],
            [
                'name' => 'Beachfront Altar Arch',
                'category' => 'ornamen-dinding', 'price' => 7500000, 'discount_pct' => 12, 'stock' => 8, 'image' => 'product-31.png',
                'description' => 'Arch altar untuk pernikahan tepi pantai dengan draping putih dan ornamen kerang.',
                'name_id' => 'Arch Altar Tepi Pantai', 'name_en' => 'Beachfront Altar Arch',
                'desc_id' => 'Arch altar untuk pernikahan tepi pantai dengan draping putih dan ornamen kerang.',
                'desc_en' => 'Altar arch for beachfront wedding with white draping and shell ornaments.',
            ],
            [
                'name' => 'Frangipani Flower Garland',
                'category' => 'hiasan-gaun', 'price' => 1200000, 'discount_pct' => 20, 'stock' => 30, 'image' => 'product-32.png',
                'description' => 'Garland bunga frangipani segar untuk dekorasi leher atau meja. Wangi khas tropis.',
                'name_id' => 'Garland Bunga Frangipani', 'name_en' => 'Frangipani Flower Garland',
                'desc_id' => 'Garland bunga frangipani segar untuk dekorasi leher atau meja. Wangi khas tropis.',
                'desc_en' => 'Fresh frangipani flower garland for neck or table decoration. Distinct tropical scent.',
            ],
            [
                'name' => 'Tiki Torch Set Outdoor',
                'category' => 'lighting', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 15, 'image' => 'product-33.png',
                'description' => 'Set tiki torch bambu 6 pcs untuk lighting pernikahan pantai.',
                'name_id' => 'Set Tiki Torch Outdoor', 'name_en' => 'Outdoor Tiki Torch Set',
                'desc_id' => 'Set tiki torch bambu 6 pcs untuk lighting pernikahan pantai.',
                'desc_en' => 'Set of 6 bamboo tiki torches for beach wedding lighting.',
            ],
            [
                'name' => 'Starfish Shell Centerpiece',
                'category' => 'dekorasi-meja', 'price' => 950000, 'discount_pct' => 22, 'stock' => 35, 'image' => 'product-34.png',
                'description' => 'Centerpiece meja dengan bintang laut, kerang, dan pasir putih. Tema pantai otentik.',
                'name_id' => 'Centerpiece Bintang Laut', 'name_en' => 'Starfish Shell Centerpiece',
                'desc_id' => 'Centerpiece meja dengan bintang laut, kerang, dan pasir putih. Tema pantai otentik.',
                'desc_en' => 'Table centerpiece with starfish, shells, and white sand. Authentic beach theme.',
            ],
            [
                'name' => 'White Muslin Draping Set',
                'category' => 'kain-dekorasi', 'price' => 2100000, 'discount_pct' => 18, 'stock' => 20, 'image' => 'product-35.png',
                'description' => 'Set draping muslin putih 5 meter untuk dekorasi altar pantai yang elegan.',
                'name_id' => 'Set Draping Muslin Putih', 'name_en' => 'White Muslin Draping Set',
                'desc_id' => 'Set draping muslin putih 5 meter untuk dekorasi altar pantai yang elegan.',
                'desc_en' => '5-meter white muslin draping set for elegant beach altar decoration.',
            ],
            [
                'name' => 'Vintage Furniture Set Parlor',
                'category' => 'dekorasi-meja', 'price' => 8500000, 'discount_pct' => 11, 'stock' => 6, 'image' => 'product-36.png',
                'description' => 'Set furnitur vintage parlor terdiri dari sofa, meja, dan kursi gaya Perancis.',
                'name_id' => 'Set Furnitur Vintage Parlor', 'name_en' => 'Vintage Parlor Furniture Set',
                'desc_id' => 'Set furnitur vintage parlor terdiri dari sofa, meja, dan kursi gaya Perancis.',
                'desc_en' => 'Vintage parlor furniture set consisting of sofa, table, and French-style chairs.',
            ],
            [
                'name' => 'Antique Gold Mirror Decor',
                'category' => 'ornamen-dinding', 'price' => 3800000, 'discount_pct' => 14, 'stock' => 10, 'image' => 'product-37.png',
                'description' => 'Cermin antik dengan bingkai emas ukir. Dekorasi elegan untuk photo booth backdrop.',
                'name_id' => 'Cermin Antik Bingkai Emas', 'name_en' => 'Antique Gold Mirror Decor',
                'desc_id' => 'Cermin antik dengan bingkai emas ukir. Dekorasi elegan untuk photo booth backdrop.',
                'desc_en' => 'Antique mirror with carved gold frame. Elegant decor for photo booth backdrop.',
            ],
            [
                'name' => 'Lace Table Runner Premium',
                'category' => 'kain-dekorasi', 'price' => 850000, 'discount_pct' => 20, 'stock' => 30, 'image' => 'product-38.png',
                'description' => 'Table runner renda premium panjang 2 meter. Sentuhan vintage yang lembut.',
                'name_id' => 'Table Runner Renda Premium', 'name_en' => 'Premium Lace Table Runner',
                'desc_id' => 'Table runner renda premium panjang 2 meter. Sentuhan vintage yang lembut.',
                'desc_en' => 'Premium lace table runner 2 meters long. A soft vintage touch.',
            ],
            [
                'name' => 'Vintage Candle Chandelier',
                'category' => 'lighting', 'price' => 4200000, 'discount_pct' => 13, 'stock' => 8, 'image' => 'product-39.png',
                'description' => 'Chandelier lilin vintage dengan ornamen besi tempa. Cocok untuk tema vintage klasik.',
                'name_id' => 'Chandelier Lilin Vintage', 'name_en' => 'Vintage Candle Chandelier',
                'desc_id' => 'Chandelier lilin vintage dengan ornamen besi tempa. Cocok untuk tema vintage klasik.',
                'desc_en' => 'Vintage candle chandelier with wrought iron ornaments. Perfect for classic vintage theme.',
            ],
            [
                'name' => 'Pastel Balloon Arch Set',
                'category' => 'aksesoris', 'price' => 1900000, 'discount_pct' => 18, 'stock' => 20, 'image' => 'product-40.png',
                'description' => 'Set arch balon pastel warna vintage rose, lavender, dan cream. Manis dan romantis.',
                'name_id' => 'Set Arch Balon Pastel', 'name_en' => 'Pastel Balloon Arch Set',
                'desc_id' => 'Set arch balon pastel warna vintage rose, lavender, dan cream. Manis dan romantis.',
                'desc_en' => 'Pastel balloon arch set in vintage rose, lavender, and cream colors. Sweet and romantic.',
            ],
            [
                'name' => 'Pipa Metal Pipe Rack',
                'category' => 'dekorasi-meja', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 12, 'image' => 'product-41.png',
                'description' => 'Rak pipa besi industrial untuk dekorasi atau display bunga. Kuat dan unik.',
                'name_id' => 'Rak Pipa Besi Industrial', 'name_en' => 'Metal Pipe Rack Industrial',
                'desc_id' => 'Rak pipa besi industrial untuk dekorasi atau display bunga. Kuat dan unik.',
                'desc_en' => 'Industrial metal pipe rack for decoration or flower display. Sturdy and unique.',
            ],
            [
                'name' => 'Exposed Brick Wall Panel',
                'category' => 'ornamen-dinding', 'price' => 2500000, 'discount_pct' => 17, 'stock' => 15, 'image' => 'product-42.png',
                'description' => 'Panel dinding bata ekspos artifisial 1x2 meter. Untuk konsep industrial loft.',
                'name_id' => 'Panel Dinding Bata Ekspos', 'name_en' => 'Exposed Brick Wall Panel',
                'desc_id' => 'Panel dinding bata ekspos artifisial 1x2 meter. Untuk konsep industrial loft.',
                'desc_en' => 'Artificial exposed brick wall panel 1x2 meters. For industrial loft concept.',
            ],
            [
                'name' => 'Edison Bulb String Light',
                'category' => 'lighting', 'price' => 1400000, 'discount_pct' => 20, 'stock' => 30, 'image' => 'product-43.png',
                'description' => 'String light edison bulb 10 lampu. Lighting hangat bergaya industrial vintage.',
                'name_id' => 'String Light Edison Bulb', 'name_en' => 'Edison Bulb String Light',
                'desc_id' => 'String light edison bulb 10 lampu. Lighting hangat bergaya industrial vintage.',
                'desc_en' => 'Edison bulb string light with 10 bulbs. Warm industrial vintage style lighting.',
            ],
            [
                'name' => 'Wooden Crate Table Set',
                'category' => 'dekorasi-meja', 'price' => 2800000, 'discount_pct' => 16, 'stock' => 15, 'image' => 'product-44.png',
                'description' => 'Set meja dari kayu crate industrial. Tinggi rendah, cocok untuk konsep raw.',
                'name_id' => 'Set Meja Kayu Crate', 'name_en' => 'Wooden Crate Table Set',
                'desc_id' => 'Set meja dari kayu crate industrial. Tinggi rendah, cocok untuk konsep raw.',
                'desc_en' => 'Table set from industrial wooden crates. Various heights, perfect for raw concept.',
            ],
            [
                'name' => 'Steel Grid Photo Display',
                'category' => 'ornamen-dinding', 'price' => 1600000, 'discount_pct' => 19, 'stock' => 20, 'image' => 'product-45.png',
                'description' => 'Display foto grid besi dengan clip dan string. Untuk galeri foto pasangan.',
                'name_id' => 'Display Foto Grid Besi', 'name_en' => 'Steel Grid Photo Display',
                'desc_id' => 'Display foto grid besi dengan clip dan string. Untuk galeri foto pasangan.',
                'desc_en' => 'Steel grid photo display with clips and strings. For couple photo gallery.',
            ],
            [
                'name' => 'Bird of Paradise Flower Set',
                'category' => 'buket-bunga', 'price' => 3500000, 'discount_pct' => 14, 'stock' => 12, 'image' => 'product-46.png',
                'description' => 'Set rangkaian bunga bird of paradise segar. Ikonik untuk dekorasi tropis.',
                'name_id' => 'Set Bunga Bird of Paradise', 'name_en' => 'Bird of Paradise Flower Set',
                'desc_id' => 'Set rangkaian bunga bird of paradise segar. Ikonik untuk dekorasi tropis.',
                'desc_en' => 'Fresh bird of paradise flower arrangement set. Iconic for tropical decor.',
            ],
            [
                'name' => 'Monstera Leaf Decoration',
                'category' => 'tanaman-hias', 'price' => 1100000, 'discount_pct' => 20, 'stock' => 25, 'image' => 'product-47.png',
                'description' => 'Daun monstera artificial premium untuk dekorasi meja dan backdrop. Ukuran besar.',
                'name_id' => 'Dekorasi Daun Monstera', 'name_en' => 'Monstera Leaf Decoration',
                'desc_id' => 'Daun monstera artificial premium untuk dekorasi meja dan backdrop. Ukuran besar.',
                'desc_en' => 'Premium artificial monstera leaves for table and backdrop decoration. Large size.',
            ],
            [
                'name' => 'Bali Stone Carving Ornament',
                'category' => 'ornamen-dinding', 'price' => 4500000, 'discount_pct' => 13, 'stock' => 8, 'image' => 'product-48.png',
                'description' => 'Ornamen ukiran batu Bali untuk dekorasi taman tropis. Motif khas Bali.',
                'name_id' => 'Ornamen Ukiran Batu Bali', 'name_en' => 'Bali Stone Carving Ornament',
                'desc_id' => 'Ornamen ukiran batu Bali untuk dekorasi taman tropis. Motif khas Bali.',
                'desc_en' => 'Balinese stone carving ornament for tropical garden decor. Distinctive Balinese motifs.',
            ],
            [
                'name' => 'Tropical Palm Leaf Arch',
                'category' => 'ornamen-dinding', 'price' => 5800000, 'discount_pct' => 12, 'stock' => 8, 'image' => 'product-49.png',
                'description' => 'Arch daun palem tropis dengan bunga heliconia dan ginger. Warna-warni ceria.',
                'name_id' => 'Arch Daun Palem Tropis', 'name_en' => 'Tropical Palm Leaf Arch',
                'desc_id' => 'Arch daun palem tropis dengan bunga heliconia dan ginger. Warna-warni ceria.',
                'desc_en' => 'Tropical palm leaf arch with heliconia and ginger flowers. Cheerful and colorful.',
            ],
            [
                'name' => 'Bamboo Water Fountain',
                'category' => 'tanaman-hias', 'price' => 3200000, 'discount_pct' => 15, 'stock' => 10, 'image' => 'product-50.png',
                'description' => 'Air mancur bambu untuk dekorasi taman tropis. Suara gemericik air yang menenangkan.',
                'name_id' => 'Air Mancur Bambu', 'name_en' => 'Bamboo Water Fountain',
                'desc_id' => 'Air mancur bambu untuk dekorasi taman tropis. Suara gemericik air yang menenangkan.',
                'desc_en' => 'Bamboo water fountain for tropical garden decoration. Calming water trickling sound.',
            ],
        ];

$discountIndexes = collect(range(0, count($products) - 1))->shuffle()->take(15)->all();

        foreach ($products as $i => $data) {
            $slug = Str::slug($data['name']);
            $discountPct = in_array($i, $discountIndexes) ? (int) ($data['discount_pct'] ?? 0) : 0;
            $discountPrice = $discountPct > 0
                ? (int) round($data['price'] * (100 - $discountPct) / 100)
                : null;

            $nameTranslations = $this->translateToAllLocales($data['name_id'], $data['name_en']);
            $descTranslations = $this->translateToAllLocales($data['desc_id'], $data['desc_en']);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'vendor_id' => null,
                    'category_id' => $cats[$data['category']]->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'name_translations' => $nameTranslations,
                    'description_translations' => $descTranslations,
                    'price' => $data['price'],
                    'discount_price' => $discountPrice,
                    'stock' => $data['stock'],
                    'is_active' => true,
                ]
            );

            $product->clearMediaCollection('product_image');

            $imageCount = 4;
            $attached = [];
            for ($n = 0; $n < $imageCount; $n++) {
                $imageName = 'product-'.((($i + $n) % count($products)) + 1).'.png';
                $imagePath = public_path('images/product/'.$imageName);
                if (!file_exists($imagePath)) {
                    continue;
                }
                try {
                    $product->addMedia($imagePath)
                        ->preservingOriginal()
                        ->toMediaCollection('product_image');
                    $attached[] = $imageName;
                } catch (\Exception $e) {
                    $this->command->error("  ✗ Gagal memuat gambar untuk: {$data['name']} [{$imageName}]");
                }
            }

            if (count($attached) > 0) {
                $this->command->line("  <info>✓</info> {$data['name']} [".implode(', ', $attached).'] ('.count($attached).' gambar)');
            } else {
                $this->command->line("  <info>✓</info> {$data['name']} (Tanpa Gambar)");
            }
        }

        $this->command->info('--- Product Seeding Complete ('.count($products).' products) ---');
    }
}
