<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\Vendor\Vendor;
use App\Traits\TranslatesContent\TranslatesContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PackageSeeder extends Seeder
{
    use TranslatesContent;

    public function run(): void
    {
        $this->command->info('--- Seeding Packages ---');

        $cats = Category::where('type', 'package')->get()->keyBy('slug');
        $vendors = Vendor::all();

        $packages = [
            [
                'name' => 'Luxurious Traditional Gebyok Package',
                'category' => 'premium', 'price' => 20000000, 'discount_pct' => 10,
                'featured' => true, 'stock' => 5, 'color' => '#8B4513', 'image' => 'package-1.png',
                'features' => ['Gebyok ukir premium', 'Bunga segar pilihan', 'Kain batik eksklusif', 'Lighting tradisional', 'Tim dekorasi profesional'],
                'description' => 'Paket dekorasi pernikahan tradisional mewah dengan gebyok ukir kayu jati pilihan, dihiasi rangkaian bunga segar dan kain batik eksklusif.',
                'name_id' => 'Paket Gebyok Tradisional Mewah', 'name_en' => 'Luxurious Traditional Gebyok Package',
                'desc_id' => 'Paket dekorasi pernikahan tradisional mewah dengan gebyok ukir kayu jati pilihan, dihiasi rangkaian bunga segar dan kain batik eksklusif.',
                'desc_en' => 'Luxurious traditional wedding decoration package with premium carved teak wood gebyok, adorned with fresh flower arrangements and exclusive batik fabrics.',
            ],
            [
                'name' => 'Javanese Royal Pendopo Package',
                'category' => 'mewah', 'price' => 23000000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#6B3A2A', 'image' => 'package-2.png',
                'features' => ['Pendopo joglo replika', 'Ornamen emas', 'Bunga melati segar', 'Backdrop batik tulis', 'Pelaminan ukir'],
                'description' => 'Nuansa pendopo joglo kerajaan Jawa yang autentik dengan ornamen emas dan rangkaian melati segar.',
                'name_id' => 'Paket Pendopo Kerajaan Jawa', 'name_en' => 'Javanese Royal Pendopo Package',
                'desc_id' => 'Nuansa pendopo joglo kerajaan Jawa yang autentik dengan ornamen emas dan rangkaian melati segar.',
                'desc_en' => 'Authentic Javanese royal pendopo joglo ambiance with golden ornaments and fresh jasmine arrangements.',
            ],
            [
                'name' => 'Batik Heritage Wedding Package',
                'category' => 'standar', 'price' => 18000000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 5, 'color' => '#A0522D', 'image' => 'package-3.png',
                'features' => ['Backdrop batik mega mendung', 'Seserahan tradisional', 'Bunga kenanga melati', 'Kain songket pelaminan', 'Rias pengantin adat'],
                'description' => 'Rayakan cinta dengan warisan batik Nusantara. Perpaduan batik mega mendung dan songket menciptakan suasana sakral dan anggun.',
                'name_id' => 'Paket Pernikahan Batik Heritage', 'name_en' => 'Batik Heritage Wedding Package',
                'desc_id' => 'Rayakan cinta dengan warisan batik Nusantara. Perpaduan batik mega mendung dan songket menciptakan suasana sakral dan anggun.',
                'desc_en' => 'Celebrate love with Nusantara batik heritage. The combination of mega mendung batik and songket creates a sacred and elegant atmosphere.',
            ],
            [
                'name' => 'Keraton Agung Majapahit Package',
                'category' => 'eksekutif', 'price' => 26000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 3, 'color' => '#DAA520', 'image' => 'package-4.png',
                'features' => ['Trono kerajaan replika', 'Ornamen emas keraton', 'Bunga cempaka segar', 'Backdrop candi bentar', 'Gamelan dekorasi', 'Batik parang rusak'],
                'description' => 'Nuansa kerajaan Majapahit yang megah dengan trono emas, ornamen keraton, dan gamelan sebagai dekorasi.',
                'name_id' => 'Paket Keraton Agung Majapahit', 'name_en' => 'Keraton Agung Majapahit Package',
                'desc_id' => 'Nuansa kerajaan Majapahit yang megah dengan trono emas, ornamen keraton, dan gamelan sebagai dekorasi.',
                'desc_en' => 'Majestic Majapahit kingdom ambiance with golden throne, palace ornaments, and gamelan decorations.',
            ],
            [
                'name' => 'Adat Betawi Elok Package',
                'category' => 'hemat', 'price' => 16500000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#CD5C5C', 'image' => 'package-5.png',
                'features' => ['Pelaminan Betawi khas', 'Bunga tanjung segar', 'Ondel-ondel mini', 'Kain kebaya encim', 'Hiasan gigi balang'],
                'description' => 'Pernikahan adat Betawi yang meriah dengan pelaminan khas Betawi, ondel-ondel mini, dan kebaya encim yang anggun.',
                'name_id' => 'Paket Adat Betawi Elok', 'name_en' => 'Adat Betawi Elok Package',
                'desc_id' => 'Pernikahan adat Betawi yang meriah dengan pelaminan khas Betawi, ondel-ondel mini, dan kebaya encim yang anggun.',
                'desc_en' => 'Festive Betawi traditional wedding with distinctive Betawi throne, mini ondel-ondel, and elegant kebaya encim.',
            ],
            [
                'name' => 'Modern Crystal Stage Package',
                'category' => 'eksekutif', 'price' => 25000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#4A90D9', 'image' => 'package-6.png',
                'features' => ['Chandelier kristal', 'Bunga anggrek putih', 'Backdrop LED', 'Karpet merah premium', 'Meja penerima tamu'],
                'description' => 'Paket dekorasi modern elegan dengan chandelier kristal berkilau dan rangkaian anggrek putih. Backdrop LED interaktif menciptakan suasana mewah.',
                'name_id' => 'Paket Panggung Kristal Modern', 'name_en' => 'Modern Crystal Stage Package',
                'desc_id' => 'Paket dekorasi modern elegan dengan chandelier kristal berkilau dan rangkaian anggrek putih. Backdrop LED interaktif menciptakan suasana mewah.',
                'desc_en' => 'Elegant modern decoration package with sparkling crystal chandeliers and white orchid arrangements. Interactive LED backdrop creates a luxurious atmosphere.',
            ],
            [
                'name' => 'Contemporary White Luxe Package',
                'category' => 'deluxe', 'price' => 27000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 5, 'color' => '#E8E8E8', 'image' => 'package-7.png',
                'features' => ['All-white concept', 'Bunga peony & mawar', 'Neon sign custom', 'Flower wall backdrop', 'Aisle dekorasi'],
                'description' => 'Konsep serba putih yang bersih dan mewah dengan bunga peony dan mawar pilihan. Neon sign custom dan flower wall backdrop.',
                'name_id' => 'Paket Mewah Putih Kontemporer', 'name_en' => 'Contemporary White Luxe Package',
                'desc_id' => 'Konsep serba putih yang bersih dan mewah dengan bunga peony dan mawar pilihan. Neon sign custom dan flower wall backdrop.',
                'desc_en' => 'Clean and luxurious all-white concept with peonies and premium roses. Custom neon sign and flower wall backdrop.',
            ],
            [
                'name' => 'Sleek Urban Loft Package',
                'category' => 'mewah', 'price' => 22000000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#2F4F4F', 'image' => 'package-8.png',
                'features' => ['Konsep loft industrial', 'Bunga monokrom', 'Geometric backdrop', 'Furniture modern', 'String lighting'],
                'description' => 'Gaya urban loft yang sleek dengan warna monokrom, geometric backdrop, dan string lighting yang hangat.',
                'name_id' => 'Paket Urban Loft Modern', 'name_en' => 'Sleek Urban Loft Package',
                'desc_id' => 'Gaya urban loft yang sleek dengan warna monokrom, geometric backdrop, dan string lighting yang hangat.',
                'desc_en' => 'Sleek urban loft style with monochrome colors, geometric backdrop, and warm string lighting.',
            ],
            [
                'name' => 'Futuristic Glass Palace Package',
                'category' => 'deluxe', 'price' => 32000000, 'discount_pct' => 6,
                'featured' => true, 'stock' => 3, 'color' => '#B0C4DE', 'image' => 'package-9.png',
                'features' => ['Dinding kaca premium', 'LED pixel mapping', 'Bunga white orchid', 'Floating decor', 'Laser lighting show'],
                'description' => 'Konsep futuristik dengan dinding kaca dan LED pixel mapping. Tampilan visual yang memukau dan modern.',
                'name_id' => 'Paket Istana Kaca Futuristik', 'name_en' => 'Futuristic Glass Palace Package',
                'desc_id' => 'Konsep futuristik dengan dinding kaca dan LED pixel mapping. Tampilan visual yang memukau dan modern.',
                'desc_en' => 'Futuristic concept with glass walls and LED pixel mapping. Stunning and modern visual display.',
            ],
            [
                'name' => 'Minimalist Monochrome Package',
                'category' => 'premium', 'price' => 19000000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#696969', 'image' => 'package-10.png',
                'features' => ['Monochrome concept', 'Bunga white anemone', 'Marble backdrop', 'Clean line furniture', 'Modern chandelier'],
                'description' => 'Keindahan monokrom yang timeless dengan white anemone, marble backdrop, dan furnitur clean line.',
                'name_id' => 'Paket Monokrom Minimalis', 'name_en' => 'Minimalist Monochrome Package',
                'desc_id' => 'Keindahan monokrom yang timeless dengan white anemone, marble backdrop, dan furnitur clean line.',
                'desc_en' => 'Timeless monochrome beauty with white anemones, marble backdrop, and clean line furniture.',
            ],
            [
                'name' => 'Rustic Sunset Garden Package',
                'category' => 'standar', 'price' => 17000000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#D2691E', 'image' => 'package-11.png',
                'features' => ['Arch kayu alami', 'Pampas grass', 'Fairy lights', 'Bunga liar segar', 'Dekorasi bambu'],
                'description' => 'Paket dekorasi rustic hangat dengan arch kayu alami, pampas grass, dan fairy lights yang romantis.',
                'name_id' => 'Paket Taman Senja Rustic', 'name_en' => 'Rustic Sunset Garden Package',
                'desc_id' => 'Paket dekorasi rustic hangat dengan arch kayu alami, pampas grass, dan fairy lights yang romantis.',
                'desc_en' => 'Warm rustic decoration package with natural wooden arch, pampas grass, and romantic fairy lights.',
            ],
            [
                'name' => 'Bohemian Wildflower Dream Package',
                'category' => 'standar', 'price' => 18500000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 5, 'color' => '#C4A35A', 'image' => 'package-12.png',
                'features' => ['Macrame backdrop', 'Bunga liar mix', 'Tipi tent dekorasi', 'Dreamcatcher ornamen', 'Karpet etnik'],
                'description' => 'Gaya bohemian bebas dengan macrame backdrop, bunga liar warna-warni, dan ornamen dreamcatcher.',
                'name_id' => 'Paket Impian Bunga Liar Bohemian', 'name_en' => 'Bohemian Wildflower Dream Package',
                'desc_id' => 'Gaya bohemian bebas dengan macrame backdrop, bunga liar warna-warni, dan ornamen dreamcatcher.',
                'desc_en' => 'Free-spirited bohemian style with macrame backdrop, colorful wildflowers, and dreamcatcher ornaments.',
            ],
            [
                'name' => 'Barnwood Countryside Package',
                'category' => 'hemat', 'price' => 15500000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#8B7355', 'image' => 'package-13.png',
                'features' => ['Barnwood backdrop', 'Bunga sunflower mix', 'Wheel barrel decor', 'Hay bale seating', 'Jute aisle runner'],
                'description' => 'Nuansa pedesaan Eropa dengan barnwood, bunga sunflower, dan dekorasi wheel barrel yang unik.',
                'name_id' => 'Paket Pedesaan Barnwood', 'name_en' => 'Barnwood Countryside Package',
                'desc_id' => 'Nuansa pedesaan Eropa dengan barnwood, bunga sunflower, dan dekorasi wheel barrel yang unik.',
                'desc_en' => 'European countryside ambiance with barnwood, sunflowers, and unique wheel barrel decorations.',
            ],
            [
                'name' => 'Vineyard Twilight Package',
                'category' => 'premium', 'price' => 21000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 4, 'color' => '#722F37', 'image' => 'package-14.png',
                'features' => ['Pergola anggur', 'Bunga lavender', 'Wine barrel decor', 'String lights warm', 'Oak table rustic'],
                'description' => 'Pernikahan romantis di kebun anggur senja dengan pergola, lavender, dan wine barrel decor.',
                'name_id' => 'Paket Senja Kebun Anggur', 'name_en' => 'Vineyard Twilight Package',
                'desc_id' => 'Pernikahan romantis di kebun anggur senja dengan pergola, lavender, dan wine barrel decor.',
                'desc_en' => 'Romantic vineyard twilight wedding with pergola, lavender, and wine barrel decor.',
            ],
            [
                'name' => 'Rustic Meadow Picnic Package',
                'category' => 'hemat', 'price' => 14000000, 'discount_pct' => 11,
                'featured' => false, 'stock' => 5, 'color' => '#9ACD32', 'image' => 'package-15.png',
                'features' => ['Picnic setup outdoor', 'Bunga wildflower mix', 'Piknik basket decor', 'Karpet tartan', 'Pillow boho'],
                'description' => 'Pernikahan santai bergaya piknik di padang rumput dengan dekorasi boho dan bunga liar.',
                'name_id' => 'Paket Piknik Padang Rumput', 'name_en' => 'Rustic Meadow Picnic Package',
                'desc_id' => 'Pernikahan santai bergaya piknik di padang rumput dengan dekorasi boho dan bunga liar.',
                'desc_en' => 'Casual picnic-style wedding in the meadow with boho decor and wildflowers.',
            ],
            [
                'name' => 'Minimalist Pastel Arch Package',
                'category' => 'hemat', 'price' => 13000000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 5, 'color' => '#F4C2C2', 'image' => 'package-16.png',
                'features' => ['Arch geometris', 'Bunga blush rose', 'Eucalyptus garland', 'Backdrop polos premium', 'Dekorasi meja simpel'],
                'description' => 'Paket dekorasi minimalis bersih dengan arch geometris, blush rose, dan eucalyptus.',
                'name_id' => 'Paket Arch Pastel Minimalis', 'name_en' => 'Minimalist Pastel Arch Package',
                'desc_id' => 'Paket dekorasi minimalis bersih dengan arch geometris, blush rose, dan eucalyptus.',
                'desc_en' => 'Clean minimalist decoration package with geometric arch, blush roses, and eucalyptus.',
            ],
            [
                'name' => 'Scandinavian Simplicity Package',
                'category' => 'complete', 'price' => 15500000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#F5F5DC', 'image' => 'package-17.png',
                'features' => ['Konsep Skandinavia', 'Bunga dried flower', 'Furniture kayu ringan', 'Textile natural', 'Lighting warm'],
                'description' => 'Desain Skandinavia yang hangat dengan dried flower, kayu ringan, dan tekstil natural.',
                'name_id' => 'Paket Simpel Skandinavia', 'name_en' => 'Scandinavian Simplicity Package',
                'desc_id' => 'Desain Skandinavia yang hangat dengan dried flower, kayu ringan, dan tekstil natural.',
                'desc_en' => 'Warm Scandinavian design with dried flowers, light wood, and natural textiles.',
            ],
            [
                'name' => 'Zen Minimalist Garden Package',
                'category' => 'premium', 'price' => 17500000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 4, 'color' => '#A9BA9D', 'image' => 'package-18.png',
                'features' => ['Konsep Zen', 'Bunga cherry blossom', 'Bamboo decor', 'Stone pathway', 'Water element'],
                'description' => 'Ketenangan taman Zen dengan cherry blossom, bambu, dan elemen air yang menenangkan.',
                'name_id' => 'Paket Taman Zen Minimalis', 'name_en' => 'Zen Minimalist Garden Package',
                'desc_id' => 'Ketenangan taman Zen dengan cherry blossom, bambu, dan elemen air yang menenangkan.',
                'desc_en' => 'Tranquil Zen garden with cherry blossoms, bamboo, and calming water elements.',
            ],
            [
                'name' => 'Clean White Sanctuary Package',
                'category' => 'premium', 'price' => 20000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#FFFAFA', 'image' => 'package-19.png',
                'features' => ['All-white floral', 'Bunga white lily', 'White draping', 'Marble accent', 'Gold geometric'],
                'description' => 'Pure white sanctuary dengan white lily, marble accent, dan gold geometric yang elegan.',
                'name_id' => 'Paket Suaka Putih Bersih', 'name_en' => 'Clean White Sanctuary Package',
                'desc_id' => 'Pure white sanctuary dengan white lily, marble accent, dan gold geometric yang elegan.',
                'desc_en' => 'Pure white sanctuary with white lilies, marble accents, and elegant gold geometrics.',
            ],
            [
                'name' => 'Boho Minimalist Terrace Package',
                'category' => 'hemat', 'price' => 14500000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#DEB887', 'image' => 'package-20.png',
                'features' => ['Boho chic style', 'Bunga pampas & lavender', 'Rattan furniture', 'Macrame accent', 'Candle lantern'],
                'description' => 'Terrace boho minimalis dengan pampas, lavender, dan furnitur rotan yang santai.',
                'name_id' => 'Paket Teras Boho Minimalis', 'name_en' => 'Boho Minimalist Terrace Package',
                'desc_id' => 'Terrace boho minimalis dengan pampas, lavender, dan furnitur rotan yang santai.',
                'desc_en' => 'Minimalist boho terrace with pampas, lavender, and relaxed rattan furniture.',
            ],
            [
                'name' => 'English Garden Romance Package',
                'category' => 'premium', 'price' => 21000000, 'discount_pct' => 10,
                'featured' => true, 'stock' => 5, 'color' => '#228B22', 'image' => 'package-21.png',
                'features' => ['Floral arch besar', 'Bunga mawar garden', 'Ivy & greenery', 'Pergola dekorasi', 'Aisle bunga segar'],
                'description' => 'Paket taman bunga Inggris yang romantis dengan floral arch besar, mawar garden, dan pergola yang dihiasi ivy.',
                'name_id' => 'Paket Romantis Taman Inggris', 'name_en' => 'English Garden Romance Package',
                'desc_id' => 'Paket taman bunga Inggris yang romantis dengan floral arch besar, mawar garden, dan pergola yang dihiasi ivy.',
                'desc_en' => 'Romantic English garden package with a large floral arch, garden roses, and an ivy-adorned pergola.',
            ],
            [
                'name' => 'Secret Garden Enchanted Package',
                'category' => 'mewah', 'price' => 23000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 4, 'color' => '#006400', 'image' => 'package-22.png',
                'features' => ['Secret garden gate', 'Bunga fairy rose', 'Moss & fern decor', 'Fairy lights canopy', 'Stone garden path'],
                'description' => 'Taman rahasia yang mempesona dengan fairy rose, moss, dan fairy lights canopy yang magis.',
                'name_id' => 'Paket Taman Rahasia Mempesona', 'name_en' => 'Secret Garden Enchanted Package',
                'desc_id' => 'Taman rahasia yang mempesona dengan fairy rose, moss, dan fairy lights canopy yang magis.',
                'desc_en' => 'Enchanted secret garden with fairy roses, moss, and a magical fairy lights canopy.',
            ],
            [
                'name' => 'Rose Garden Paradise Package',
                'category' => 'eksekutif', 'price' => 25000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 4, 'color' => '#DC143C', 'image' => 'package-23.png',
                'features' => ['Taman mawar ribuan', 'Bunga mawar rainbow', 'Gazebo putih', 'Aisle kelopak mawar', 'Air mancur'],
                'description' => 'Surga taman mawar dengan ribuan mawar rainbow, gazebo putih, dan air mancur yang menenangkan.',
                'name_id' => 'Paket Surga Taman Mawar', 'name_en' => 'Rose Garden Paradise Package',
                'desc_id' => 'Surga taman mawar dengan ribuan mawar rainbow, gazebo putih, dan air mancur yang menenangkan.',
                'desc_en' => 'Rose garden paradise with thousands of rainbow roses, white gazebo, and a calming fountain.',
            ],
            [
                'name' => 'Tropical Garden Oasis Package',
                'category' => 'premium', 'price' => 19500000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#32CD32', 'image' => 'package-24.png',
                'features' => ['Tropical foliage', 'Bunga hibiscus & frangipani', 'Bamboo pergola', 'Koi pond decor', 'Thatched roof'],
                'description' => 'Oase taman tropis dengan foliage lebat, bunga kembang sepatu, dan pergola bambu yang eksotis.',
                'name_id' => 'Paket Oase Taman Tropis', 'name_en' => 'Tropical Garden Oasis Package',
                'desc_id' => 'Oase taman tropis dengan foliage lebat, bunga kembang sepatu, dan pergola bambu yang eksotis.',
                'desc_en' => 'Tropical garden oasis with lush foliage, hibiscus flowers, and exotic bamboo pergola.',
            ],
            [
                'name' => 'Lavender Sunset Garden Package',
                'category' => 'standar', 'price' => 17500000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#8A2BE2', 'image' => 'package-25.png',
                'features' => ['Lavender garden', 'Bunga lavender segar', 'Purple draping', 'Twilight lighting', 'Provencal decor'],
                'description' => 'Taman lavender senja yang romantis dengan nuansa ungu dan lighting senja yang hangat.',
                'name_id' => 'Paket Taman Lavender Senja', 'name_en' => 'Lavender Sunset Garden Package',
                'desc_id' => 'Taman lavender senja yang romantis dengan nuansa ungu dan lighting senja yang hangat.',
                'desc_en' => 'Romantic lavender sunset garden with purple hues and warm twilight lighting.',
            ],
            [
                'name' => 'Grand Royal Ballroom Package',
                'category' => 'royal', 'price' => 45000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 3, 'color' => '#FFD700', 'image' => 'package-26.png',
                'features' => ['Pelaminan emas mewah', 'Chandelier grand', 'Bunga premium import', 'Red carpet VIP', 'Dekorasi ceiling penuh', 'Tim 20 orang'],
                'description' => 'Paket kemewahan ballroom kerajaan dengan pelaminan emas, chandelier grand, dan bunga premium import.',
                'name_id' => 'Paket Ballroom Kerajaan Grand', 'name_en' => 'Grand Royal Ballroom Package',
                'desc_id' => 'Paket kemewahan ballroom kerajaan dengan pelaminan emas, chandelier grand, dan bunga premium import.',
                'desc_en' => 'Royal ballroom luxury package with golden throne, grand chandeliers, and imported premium flowers.',
            ],
            [
                'name' => 'Versailles Gold Elegance Package',
                'category' => 'royal', 'price' => 50000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 2, 'color' => '#B8860B', 'image' => 'package-27.png',
                'features' => ['Konsep istana Versailles', 'Ornamen emas 24k', 'Bunga mawar merah premium', 'Candelabra set', 'Ceiling draping mewah', 'Lighting show'],
                'description' => 'Terinspirasi kemewahan Istana Versailles dengan ornamen emas, mawar merah premium, dan ceiling draping yang dramatis.',
                'name_id' => 'Paket Keanggunan Emas Versailles', 'name_en' => 'Versailles Gold Elegance Package',
                'desc_id' => 'Terinspirasi kemewahan Istana Versailles dengan ornamen emas, mawar merah premium, dan ceiling draping yang dramatis.',
                'desc_en' => 'Inspired by the luxury of Versailles Palace with gold ornaments, premium red roses, and dramatic ceiling draping.',
            ],
            [
                'name' => 'Imperial Palace Majestic Package',
                'category' => 'royal', 'price' => 55000000, 'discount_pct' => 6,
                'featured' => true, 'stock' => 2, 'color' => '#C41E3A', 'image' => 'package-28.png',
                'features' => ['Konsep istana China', 'Ornamen naga emas', 'Bunga peony merah', 'Lantern Chinese red', 'Silk draping premium'],
                'description' => 'Kemegahan istana kekaisaran China dengan nuansa merah emas, naga ornamen, dan peony merah.',
                'name_id' => 'Paket Megah Istana Kekaisaran', 'name_en' => 'Imperial Palace Majestic Package',
                'desc_id' => 'Kemegahan istana kekaisaran China dengan nuansa merah emas, naga ornamen, dan peony merah.',
                'desc_en' => 'Majestic Chinese imperial palace with red-gold hues, dragon ornaments, and red peonies.',
            ],
            [
                'name' => 'Arabian Nights Luxe Package',
                'category' => 'royal', 'price' => 48000000, 'discount_pct' => 7,
                'featured' => false, 'stock' => 2, 'color' => '#4B0082', 'image' => 'package-29.png',
                'features' => ['Konsep Arabian', 'Gold arabesque decor', 'Bunga gardenia & jasmine', 'Silk tent draping', 'Crystal chandelier', 'Hookah corner'],
                'description' => 'Kemewahan seribu satu malam Arab dengan arabesque emas, silk tent, dan chandelier kristal.',
                'name_id' => 'Paket Mewah Arabian Nights', 'name_en' => 'Arabian Nights Luxe Package',
                'desc_id' => 'Kemewahan seribu satu malam Arab dengan arabesque emas, silk tent, dan chandelier kristal.',
                'desc_en' => 'Arabian Nights luxury with golden arabesque, silk tent, and crystal chandeliers.',
            ],
            [
                'name' => 'Baroque Golden Era Package',
                'category' => 'royal', 'price' => 42000000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 3, 'color' => '#8B0000', 'image' => 'package-30.png',
                'features' => ['Konsep Baroque Eropa', 'Gold leaf ornament', 'Bunga burgundy rose', 'Velvet draping', 'Candelabra antique', 'Classic painting backdrop'],
                'description' => 'Era keemasan Baroque Eropa dengan gold leaf, burgundy rose, dan velvet draping yang mewah.',
                'name_id' => 'Paket Era Emas Baroque', 'name_en' => 'Baroque Golden Era Package',
                'desc_id' => 'Era keemasan Baroque Eropa dengan gold leaf, burgundy rose, dan velvet draping yang mewah.',
                'desc_en' => 'European golden Baroque era with gold leaf, burgundy roses, and luxurious velvet draping.',
            ],
            [
                'name' => 'Tropical Beachfront Bliss Package',
                'category' => 'mewah', 'price' => 22000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#00CED1', 'image' => 'package-31.png',
                'features' => ['Beachfront altar', 'Bunga frangipani', 'Shell & starfish decor', 'White draping billow', 'Coconut lantern'],
                'description' => 'Pernikahan di tepi pantai tropis dengan altar menghadap laut, frangipani, dan dekorasi kerang.',
                'name_id' => 'Paket Bahagia Tepi Pantai Tropis', 'name_en' => 'Tropical Beachfront Bliss Package',
                'desc_id' => 'Pernikahan di tepi pantai tropis dengan altar menghadap laut, frangipani, dan dekorasi kerang.',
                'desc_en' => 'Tropical beachfront wedding with ocean-facing altar, frangipani, and shell decorations.',
            ],
            [
                'name' => 'Sunset Cliff Romance Package',
                'category' => 'deluxe', 'price' => 28000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 3, 'color' => '#FF6347', 'image' => 'package-32.png',
                'features' => ['Cliffside altar', 'Bunga orange lily', 'Tiki torch lighting', 'Rattan aisle decor', 'Sunset photo spot'],
                'description' => 'Pernikahan romantis di tebing karang dengan pemandangan sunset yang spektakuler.',
                'name_id' => 'Paket Romantis Tebing Senja', 'name_en' => 'Sunset Cliff Romance Package',
                'desc_id' => 'Pernikahan romantis di tebing karang dengan pemandangan sunset yang spektakuler.',
                'desc_en' => 'Romantic cliffside wedding with spectacular sunset views.',
            ],
            [
                'name' => 'Nautical Coastal Charm Package',
                'category' => 'premium', 'price' => 19000000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#4169E1', 'image' => 'package-33.png',
                'features' => ['Konsep nautical', 'Bunga blue hydrangea', 'Rope & anchor decor', 'Wooden barrel table', 'Striped blue white'],
                'description' => 'Pesona pesisir dengan tema nautical biru putih, hydrangea, dan dekorasi tali kapal.',
                'name_id' => 'Paket Pesona Pesisir Nautical', 'name_en' => 'Nautical Coastal Charm Package',
                'desc_id' => 'Pesona pesisir dengan tema nautical biru putih, hydrangea, dan dekorasi tali kapal.',
                'desc_en' => 'Coastal charm with blue-white nautical theme, hydrangeas, and ship rope decorations.',
            ],
            [
                'name' => 'Island Paradise Escape Package',
                'category' => 'eksekutif', 'price' => 25000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 4, 'color' => '#7FFFD4', 'image' => 'package-34.png',
                'features' => ['Pulau pribadi konsep', 'Bunga tropical mix', 'Bamboo gazebo', 'White sand aisle', 'Coconut bar'],
                'description' => 'Escape ke pulau pribadi dengan gazebo bambu, bunga tropis, dan aisle pasir putih.',
                'name_id' => 'Paket Pelarian Pulau Surga', 'name_en' => 'Island Paradise Escape Package',
                'desc_id' => 'Escape ke pulau pribadi dengan gazebo bambu, bunga tropis, dan aisle pasir putih.',
                'desc_en' => 'Private island escape with bamboo gazebo, tropical flowers, and white sand aisle.',
            ],
            [
                'name' => 'Seashore Breeze Package',
                'category' => 'standar', 'price' => 16000000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#87CEEB', 'image' => 'package-35.png',
                'features' => ['Seaside simple altar', 'Bunga baby breath', 'Seaglass decor', 'Muslin draping', 'Driftwood accent'],
                'description' => 'Pernikahan pantai sederhana dengan baby breath, seaglass, dan driftwood yang natural.',
                'name_id' => 'Paket Angin Laut Segar', 'name_en' => 'Seashore Breeze Package',
                'desc_id' => 'Pernikahan pantai sederhana dengan baby breath, seaglass, dan driftwood yang natural.',
                'desc_en' => 'Simple seaside wedding with baby breath, seaglass, and natural driftwood.',
            ],
            [
                'name' => 'Vintage Parisian Garden Package',
                'category' => 'premium', 'price' => 20000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#E6C3C3', 'image' => 'package-36.png',
                'features' => ['Konsep Parisian', 'Bunga lavender & rose', 'Vintage furniture', 'Eiffel tower mini', 'Pastel color palette'],
                'description' => 'Taman Paris vintage yang romantis dengan furnitur vintage, lavender, dan palet pastel.',
                'name_id' => 'Paket Taman Paris Vintage', 'name_en' => 'Vintage Parisian Garden Package',
                'desc_id' => 'Taman Paris vintage yang romantis dengan furnitur vintage, lavender, dan palet pastel.',
                'desc_en' => 'Romantic vintage Parisian garden with vintage furniture, lavender, and pastel palette.',
            ],
            [
                'name' => 'Retro Classic Love Package',
                'category' => 'complete', 'price' => 16000000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#F4A460', 'image' => 'package-37.png',
                'features' => ['Retro 50s style', 'Bunga carnation mix', 'Vintage car decor', 'Record player', 'Checkerboard floor'],
                'description' => 'Gaya retro 50-an yang ceria dengan dekorasi mobil vintage, record player, dan checkerboard.',
                'name_id' => 'Paket Cinta Retro Klasik', 'name_en' => 'Retro Classic Love Package',
                'desc_id' => 'Gaya retro 50-an yang ceria dengan dekorasi mobil vintage, record player, dan checkerboard.',
                'desc_en' => 'Cheerful 50s retro style with vintage car decor, record player, and checkerboard.',
            ],
            [
                'name' => 'Antique Lace Romance Package',
                'category' => 'standar', 'price' => 18000000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 4, 'color' => '#FFF0F5', 'image' => 'package-38.png',
                'features' => ['Lace premium decor', 'Bunga dusty miller', 'Pearl ornament', 'Antique mirror', 'Candle vintage'],
                'description' => 'Romansa renda antik dengan lace premium, dusty miller, dan ornamen mutiara yang elegan.',
                'name_id' => 'Paket Romansa Renda Antik', 'name_en' => 'Antique Lace Romance Package',
                'desc_id' => 'Romansa renda antik dengan lace premium, dusty miller, dan ornamen mutiara yang elegan.',
                'desc_en' => 'Antique lace romance with premium lace, dusty miller, and elegant pearl ornaments.',
            ],
            [
                'name' => 'Old Hollywood Glamour Package',
                'category' => 'vip', 'price' => 27000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 3, 'color' => '#C0C0C0', 'image' => 'package-39.png',
                'features' => ['Konsep Hollywood glam', 'Bunga white gardenia', 'Gold mirror decor', 'Velvet red curtain', 'Marquee light sign'],
                'description' => 'Kemewahan Old Hollywood dengan gardenia putih, velvet curtain, dan marquee light sign.',
                'name_id' => 'Paket Glamour Hollywood Klasik', 'name_en' => 'Old Hollywood Glamour Package',
                'desc_id' => 'Kemewahan Old Hollywood dengan gardenia putih, velvet curtain, dan marquee light sign.',
                'desc_en' => 'Old Hollywood glamour with white gardenias, velvet curtains, and marquee light signs.',
            ],
            [
                'name' => 'Victorian Rose Elegance Package',
                'category' => 'mewah', 'price' => 23000000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 4, 'color' => '#800020', 'image' => 'package-40.png',
                'features' => ['Konsep Victorian', 'Bunga burgundy rose', 'Chandelier vintage', 'Wallpaper floral', 'Tea set antique'],
                'description' => 'Keanggunan era Victorian dengan burgundy rose, chandelier vintage, dan wallpaper floral.',
                'name_id' => 'Paket Keanggunan Mawar Victorian', 'name_en' => 'Victorian Rose Elegance Package',
                'desc_id' => 'Keanggunan era Victorian dengan burgundy rose, chandelier vintage, dan wallpaper floral.',
                'desc_en' => 'Victorian era elegance with burgundy roses, vintage chandelier, and floral wallpaper.',
            ],
            [
                'name' => 'Industrial Loft Chic Package',
                'category' => 'vip', 'price' => 20000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#484848', 'image' => 'package-41.png',
                'features' => ['Brick wall exposed', 'Bunga protea mix', 'Metal pipe decor', 'Edison bulb lighting', 'Concrete floor'],
                'description' => 'Loft industrial chic dengan brick wall exposed, protea, dan edison bulb lighting.',
                'name_id' => 'Paket Industrial Loft Chic', 'name_en' => 'Industrial Loft Chic Package',
                'desc_id' => 'Loft industrial chic dengan brick wall exposed, protea, dan edison bulb lighting.',
                'desc_en' => 'Industrial loft chic with exposed brick walls, proteas, and edison bulb lighting.',
            ],
            [
                'name' => 'Brick Wall Urban Package',
                'category' => 'custom', 'price' => 17000000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#8B4513', 'image' => 'package-42.png',
                'features' => ['Brick wall gallery', 'Bunga sunflower wild', 'Steel frame decor', 'Pallet wood table', 'Street art mural'],
                'description' => 'Urban brick wall gallery dengan sunflower, steel frame, dan street art yang edgy.',
                'name_id' => 'Paket Urban Brick Wall', 'name_en' => 'Brick Wall Urban Package',
                'desc_id' => 'Urban brick wall gallery dengan sunflower, steel frame, dan street art yang edgy.',
                'desc_en' => 'Urban brick wall gallery with sunflowers, steel frames, and edgy street art.',
            ],
            [
                'name' => 'Warehouse Raw Beauty Package',
                'category' => 'custom', 'price' => 18500000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#696969', 'image' => 'package-43.png',
                'features' => ['Raw warehouse concept', 'Bunga eucalyptus & thistle', 'Metal grid decor', 'Crate wood table', 'Hanging vines'],
                'description' => 'Keindahan mentah warehouse dengan eucalyptus, metal grid, dan hanging vines.',
                'name_id' => 'Paket Keindahan Mentah Warehouse', 'name_en' => 'Warehouse Raw Beauty Package',
                'desc_id' => 'Keindahan mentah warehouse dengan eucalyptus, metal grid, dan hanging vines.',
                'desc_en' => 'Raw warehouse beauty with eucalyptus, metal grids, and hanging vines.',
            ],
            [
                'name' => 'Modern Factory Exposed Package',
                'category' => 'mewah', 'price' => 22000000, 'discount_pct' => 7,
                'featured' => false, 'stock' => 4, 'color' => '#A9A9A9', 'image' => 'package-44.png',
                'features' => ['Factory exposed ceiling', 'Bunga anthurium exotic', 'Chain & pulley decor', 'Catwalk stage', 'Industrial fan'],
                'description' => 'Factory modern dengan exposed ceiling, anthurium eksotis, dan catwalk stage yang dramatis.',
                'name_id' => 'Paket Pabrik Modern Ekspos', 'name_en' => 'Modern Factory Exposed Package',
                'desc_id' => 'Factory modern dengan exposed ceiling, anthurium eksotis, dan catwalk stage yang dramatis.',
                'desc_en' => 'Modern factory with exposed ceiling, exotic anthuriums, and dramatic catwalk stage.',
            ],
            [
                'name' => 'Steel and Glass Minimal Package',
                'category' => 'vip', 'price' => 24000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 4, 'color' => '#2E8B57', 'image' => 'package-45.png',
                'features' => ['Steel glass structure', 'Bunga succulent mix', 'Geometric steel decor', 'Glass partition', 'Green plant wall'],
                'description' => 'Struktur baja dan kaca minimalis dengan succulent, geometric steel, dan green plant wall.',
                'name_id' => 'Paket Baja dan Kaca Minimal', 'name_en' => 'Steel and Glass Minimal Package',
                'desc_id' => 'Struktur baja dan kaca minimalis dengan succulent, geometric steel, dan green plant wall.',
                'desc_en' => 'Minimalist steel and glass structure with succulents, geometric steel, and green plant wall.',
            ],
            [
                'name' => 'Tropical Paradise Garden Package',
                'category' => 'premium', 'price' => 21000000, 'discount_pct' => 8,
                'featured' => true, 'stock' => 5, 'color' => '#00FF7F', 'image' => 'package-46.png',
                'features' => ['Tropical garden lush', 'Bunga bird of paradise', 'Monstera leaves decor', 'Bali stone carving', 'Waterfall backdrop'],
                'description' => 'Taman tropis yang rimbun dengan bird of paradise, monstera, dan backdrop air terjun.',
                'name_id' => 'Paket Taman Surga Tropis', 'name_en' => 'Tropical Paradise Garden Package',
                'desc_id' => 'Taman tropis yang rimbun dengan bird of paradise, monstera, dan backdrop air terjun.',
                'desc_en' => 'Lush tropical garden with bird of paradise, monstera, and waterfall backdrop.',
            ],
            [
                'name' => 'Balinese Resort Style Package',
                'category' => 'eksekutif', 'price' => 25000000, 'discount_pct' => 7,
                'featured' => true, 'stock' => 4, 'color' => '#DEB887', 'image' => 'package-47.png',
                'features' => ['Konsep resort Bali', 'Bunga frangipani & lotus', 'Bale bangung pavilion', 'Candi batu', 'Kain tenun decor', 'Lotus pond'],
                'description' => 'Resort Bali yang mewah dengan frangipani, bale bangung pavilion, dan kolam lotus.',
                'name_id' => 'Paket Gaya Resort Bali', 'name_en' => 'Balinese Resort Style Package',
                'desc_id' => 'Resort Bali yang mewah dengan frangipani, bale bangung pavilion, dan kolam lotus.',
                'desc_en' => 'Luxurious Balinese resort with frangipani, bale bangung pavilion, and lotus pond.',
            ],
            [
                'name' => 'Exotic Rainforest Theme Package',
                'category' => 'mewah', 'price' => 23000000, 'discount_pct' => 8,
                'featured' => false, 'stock' => 4, 'color' => '#228B22', 'image' => 'package-48.png',
                'features' => ['Rainforest concept', 'Bunga orchid exotic', 'Fern & moss wall', 'Wooden rope bridge', 'Jungle animal decor'],
                'description' => 'Hutan hujan tropis yang eksotis dengan orchid, fern, dan jembatan tali kayu.',
                'name_id' => 'Paket Hutan Hujan Eksotis', 'name_en' => 'Exotic Rainforest Theme Package',
                'desc_id' => 'Hutan hujan tropis yang eksotis dengan orchid, fern, dan jembatan tali kayu.',
                'desc_en' => 'Exotic rainforest with orchids, ferns, and wooden rope bridge.',
            ],
            [
                'name' => 'Island Tropical Flower Package',
                'category' => 'complete', 'price' => 18000000, 'discount_pct' => 10,
                'featured' => false, 'stock' => 5, 'color' => '#FF69B4', 'image' => 'package-49.png',
                'features' => ['Tropical flower mix', 'Bunga heliconia & ginger', 'Palm leaf decor', 'Coconut centerpiece', 'Hibiscus garland'],
                'description' => 'Ledakan warna bunga tropis dengan heliconia, ginger, dan palm leaf yang ceria.',
                'name_id' => 'Paket Bunga Tropis Pulau', 'name_en' => 'Island Tropical Flower Package',
                'desc_id' => 'Ledakan warna bunga tropis dengan heliconia, ginger, dan palm leaf yang ceria.',
                'desc_en' => 'Explosion of tropical flower colors with heliconia, ginger, and cheerful palm leaves.',
            ],
            [
                'name' => 'Jungle Green Wonder Package',
                'category' => 'premium', 'price' => 19500000, 'discount_pct' => 9,
                'featured' => false, 'stock' => 5, 'color' => '#006400', 'image' => 'package-50.png',
                'features' => ['Jungle green decor', 'Bunga anthurium red', 'Philodendron wall', 'Tree trunk altar', 'Hanging fern garden'],
                'description' => 'Keajaiban hutan hijau dengan anthurium merah, philodendron wall, dan altar batang pohon.',
                'name_id' => 'Paket Keajaiban Hijau Hutan', 'name_en' => 'Jungle Green Wonder Package',
                'desc_id' => 'Keajaiban hutan hijau dengan anthurium merah, philodendron wall, dan altar batang pohon.',
                'desc_en' => 'Green jungle wonder with red anthuriums, philodendron wall, and tree trunk altar.',
            ],
        ];

$discountIndexes = collect(range(0, count($packages) - 1))->shuffle()->take(10)->all();

        foreach ($packages as $i => $data) {
            $slug = Str::slug($data['name']);
            $discountPct = in_array($i, $discountIndexes) ? (int) ($data['discount_pct'] ?? 0) : 0;
            $discountPrice = $discountPct > 0
                ? (int) round($data['price'] * (100 - $discountPct) / 100)
                : null;

            $nameTranslations = $this->translateToAllLocales($data['name_id'], $data['name_en']);
            $descTranslations = $this->translateToAllLocales($data['desc_id'], $data['desc_en']);

            $package = Package::updateOrCreate(
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
                    'is_featured' => $data['featured'],
                    'features' => $data['features'],
                    'color' => $data['color'],
                    'stock' => $data['stock'],
                    'is_active' => true,
                ]
            );

            $package->clearMediaCollection('package_image');

            $imageCount = 4;
            $attached = [];
            for ($n = 0; $n < $imageCount; $n++) {
                $imageName = 'package-'.((($i + $n) % count($packages)) + 1).'.png';
                $imagePath = public_path('images/package/'.$imageName);
                if (!file_exists($imagePath)) {
                    continue;
                }
                try {
                    $package->addMedia($imagePath)
                        ->preservingOriginal()
                        ->toMediaCollection('package_image');
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

        $this->command->info('--- Package Seeding Complete ('.count($packages).' packages) ---');
    }
}
