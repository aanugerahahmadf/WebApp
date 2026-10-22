<?php

namespace App\Console\Commands\ConsolidateCatalog;

use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Collapses the old two-entity model into a single Catalog with tiers.
 *
 * Before:  50 Catalog rows + 50 Package rows (both browsable, both with photos)
 * After:   100 Catalog rows (the 50 packages are promoted, photos moved across)
 *          + 5 tier rows per Catalog, no photos, no standalone pages
 *
 * Photos are *moved* at the database level (model_type / model_id /
 * collection_name) rather than re-uploaded, so no extra bytes land on disk.
 */
class ConsolidateCatalog extends Command
{
    protected $signature = 'catalog:consolidate
                            {--promote-packages : Promote Package rows into Catalog rows (moves their photos)}
                            {--tiers : Create the 5 standard tier definitions for every Catalog}
                            {--skip-existing : Leave catalogs that already have tiers alone}
                            {--dry : Report what would change without writing}';

    protected $description = 'Promote packages into catalogs and generate the Standar/Sedang/Pelaminan/Tunangan/Lengkap tier set.';

    /**
     * The five choices shown above the Order now / Add to Cart buttons.
     *
     * `multiplier` is applied to the catalog's own price, which keeps the tiers
     * sensible for all 100 catalogs without hand-pricing 500 rows.
     *
     * `desc_id` / `desc_en` are templates: `{name}` and `{category}` are
     * substituted with the catalog's own name and category so that every one of
     * the 500 tiers ends up with a description unique to its catalog.
     */
    protected const TIERS = [
        1 => [
            'key' => 'standar',
            'name_id' => 'Paket Bunga Dekorasi Standar',
            'name_en' => 'Standard Flower Decoration Package',
            'multiplier' => 0.75,
            'desc_id' => 'Paket bunga dekorasi Standar untuk {name} pada kategori {category}. Cakupannya satu titik fokus bunga di pelaminan dan satu meja tamu utama yang dirangkai dari bunga segar musiman pilihan. Cocok untuk acara intimate dengan presupuesto terbatas.',
            'desc_en' => 'A Standard flower decoration package for {name} in the {category} category. It covers a single floral focal point on the pelaminan and one main guest table, arranged from seasonal fresh blooms. Best suited to intimate events with a limited budget.',
            'features_id' => ['1 titik fokus bunga', '1 meja tamu utama', 'Vas dan bahan dasar', 'Setup 1 jam sebelum acara'],
            'features_en' => ['1 floral focal point', '1 main guest table', 'Vase and basic materials', 'Setup 1 hour before the event'],
        ],
        2 => [
            'key' => 'sedang',
            'name_id' => 'Paket Bunga Dekorasi Sedang',
            'name_en' => 'Medium Flower Decoration Package',
            'multiplier' => 1.00,
            'desc_id' => 'Paket bunga dekorasi Sedang untuk {name} pada kategori {category}. Pilihan paling seimbang: pelaminan terdekorasi ditambah dua sudut foto yang estetik, dilengkapi backdrop khusus dan dua meja tamu.',
            'desc_en' => 'A Medium flower decoration package for {name} in the {category} category. The balanced choice: a decorated pelaminan plus two photogenic corners, including a dedicated backdrop and two guest tables.',
            'features_id' => ['Pelaminan dan 2 sudut foto', 'Backdrop khusus', 'Hingga 2 meja tamu', 'Urn premium', 'Setup 2 jam sebelum acara'],
            'features_en' => ['Pelaminan and 2 photo corners', 'Dedicated backdrop', 'Up to 2 guest tables', 'Premium urns', 'Setup 2 hours before the event'],
        ],
        3 => [
            'key' => 'pelaminan',
            'name_id' => 'Paket Bunga Dekorasi Pelaminan atau Pernikahan',
            'name_en' => 'Ceremony or Wedding Flower Decoration Package',
            'multiplier' => 1.40,
            'desc_id' => 'Paket bunga dekorasi Pelaminan atau Pernikahan untuk {name} pada kategori {category}. Fokus pada hari pernikahan: dekorasi aisle dan panggung, gerbang selamat datang, hingga sepuluh meja tamu dengan rangkaian bunga penuh.',
            'desc_en' => 'A Ceremony or Wedding flower decoration package for {name} in the {category} category. Focused on the wedding day: ceremony aisle and stage decoration, a welcome gate, and up to ten guest tables with full floral runs.',
            'features_id' => ['Dekorasi aisle pelaminan', 'Panggung dan gerbang welcome', 'Hingga 10 meja tamu', 'Rangkaian bunga penuh', 'Konsultasi desain 2 kali', 'Setup 3 jam sebelum acara'],
            'features_en' => ['Ceremony aisle decoration', 'Stage and welcome gate', 'Up to 10 guest tables', 'Full floral runs', '2x design consultation', 'Setup 3 hours before the event'],
        ],
        4 => [
            'key' => 'tunjungan',
            'name_id' => 'Paket Bunga Dekorasi Tunangan',
            'name_en' => 'Engagement Flower Decoration Package',
            'multiplier' => 1.25,
            'desc_id' => 'Paket bunga dekorasi Tunangan untuk {name} pada kategori {category}. Dibuat untuk acara tunangan: nuansa lembut dan romantis dengan satu bouquet centerpiece di pelaminan, backdrop bertema, dan hingga enam meja tamu termasuk meja akad.',
            'desc_en' => 'An Engagement flower decoration package for {name} in the {category} category. Made for engagement parties: softer, more romantic tones with a single bouquet centerpiece on the pelaminan, a themed backdrop, and up to six guest tables including the akad table.',
            'features_id' => ['Bouquet centerpiece pelaminan', 'Backdrop bertema', 'Hingga 6 meja tamu', 'Dekorasi meja akad', 'Setup 2 jam sebelum acara'],
            'features_en' => ['Bouquet centerpiece on the pelaminan', 'Themed backdrop', 'Up to 6 guest tables', 'Akad table decoration', 'Setup 2 hours before the event'],
        ],
        5 => [
            'key' => 'lengkap',
            'name_id' => 'Paket Bunga Dekorasi Lengkap',
            'name_en' => 'Full Flower Decoration Package',
            'multiplier' => 1.85,
            'desc_id' => 'Paket bunga dekorasi Lengkap untuk {name} pada kategori {category}. Pilihan paling lengkap: seluruh area acara ditata dari pelaminan, meja tamu, doorway, hingga lounge memakai materi premium import dan desain bunga penuh.',
            'desc_en' => 'A Full flower decoration package for {name} in the {category} category. The most complete option: every area of the venue is styled from the pelaminan and guest tables to the doorway and lounge, using imported premium materials and full floral design.',
            'features_id' => ['Semua area didekorasi', 'Pelaminan, doorway, dan lounge', 'Hingga 15 meja tamu', 'Materi premium import', 'Konsultasi desain 3 kali', 'Tim tambahan saat acara', 'Setup 4 jam sebelum acara'],
            'features_en' => ['Every area decorated', 'Pelaminan, doorway, and lounge', 'Up to 15 guest tables', 'Imported premium materials', '3x design consultation', 'Extra crew on event day', 'Setup 4 hours before the event'],
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        if (! $this->option('promote-packages') && ! $this->option('tiers')) {
            $this->components->error('Pilih minimal satu flag: --promote-packages atau --tiers');

            return self::FAILURE;
        }

        if ($this->option('promote-packages')) {
            $this->promotePackages($dry);
        }

        if ($this->option('tiers')) {
            $this->createTiers($dry);
        }

        $this->newLine();
        $this->components->twoColumnDetail('Catalog', (string) Catalog::count());
        $this->components->twoColumnDetail('Tier (packages)', (string) Package::count());
        $this->components->twoColumnDetail('Catalog tanpa tier', (string) Catalog::doesntHave('tiers')->count());
        $this->components->twoColumnDetail('Catalog tanpa foto', (string) Catalog::whereDoesntHave('media')->count());

        return self::SUCCESS;
    }

    /**
     * Turn every Package into a first-class Catalog.
     *
     * Photos are relinked in place (UPDATE media SET model_type/model_id/
     * collection_name) so the ~170 MB of PNG is never copied or re-encoded.
     */
    protected function promotePackages(bool $dry): void
    {
        $packages = Package::query()->whereNull('catalog_id')->with('media')->orderBy('id')->get();

        $this->components->info(sprintf(
            'Mempromosikan %d Package menjadi Catalog%s.',
            $packages->count(),
            $dry ? ' (dry-run)' : ''
        ));

        if ($packages->isEmpty()) {
            return;
        }

        $bar = $this->output->createProgressBar($packages->count());
        $bar->start();

        foreach ($packages as $package) {
            if (! $dry) {
                $this->promoteOne($package);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    protected function promoteOne(Package $package): Catalog
    {
        // Prefix keeps the promoted slug recognisable and guarantees no clash
        // with the 50 rows that were already catalogs.
        $baseSlug = Str::slug($package->slug ?: $package->name);
        $slug = 'c-'.$baseSlug;
        $suffix = 2;
        while (Catalog::where('slug', $slug)->exists()) {
            $slug = 'c-'.$baseSlug.'-'.$suffix++;
        }

        $catalog = Catalog::updateOrCreate(
            ['slug' => $slug],
            [
                'category_id' => $package->category_id,
                'name' => $package->name,
                'description' => $package->description,
                'name_translations' => $package->name_translations,
                'description_translations' => $package->description_translations,
                'price' => $package->price,
                'discount_price' => $package->discount_price,
                'stock' => max(1, (int) $package->stock),
                'features' => $package->features,
                'theme' => $package->theme,
                'color' => $package->color,
                'min_capacity' => $package->min_capacity,
                'max_capacity' => $package->max_capacity,
                'is_featured' => (bool) $package->is_featured,
                'is_active' => true,
            ]
        );

        $this->movePhotos($package, $catalog);

        // The package row has been folded into the catalog; drop it so it does
        // not come back as an orphan tier on the next run.
        $package->delete();

        return $catalog;
    }

    /**
     * Relink media rows onto the new catalog. No file is touched, so this is
     * instantaneous and costs zero extra disk.
     */
    protected function movePhotos(Package $package, Catalog $catalog): void
    {
        $photos = Media::query()
            ->where('model_type', $package->getMorphClass())
            ->where('model_id', $package->getKey())
            ->orderBy('order_column')
            ->orderBy('id')
            ->get();

        if ($photos->isEmpty()) {
            return;
        }

        // Continue the ordering after whatever the catalog already has.
        $offset = (int) Media::query()
            ->where('model_type', $catalog->getMorphClass())
            ->where('model_id', $catalog->getKey())
            ->max('order_column');

        foreach ($photos->values() as $index => $media) {
            $media->forceFill([
                'model_type' => $catalog->getMorphClass(),
                'model_id' => $catalog->getKey(),
                'collection_name' => 'catalog_image',
                'order_column' => $offset + $index + 1,
            ])->save();
        }
    }

    /**
     * Create the five standard tiers for every catalog.
     */
    protected function createTiers(bool $dry): void
    {
        $skipExisting = (bool) $this->option('skip-existing');

        $catalogs = Catalog::query()
            ->when($skipExisting, fn ($q) => $q->whereHas('tiers'))
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->components->info(sprintf(
            'Membuat %d tier untuk %d Catalog%s.',
            $catalogs->count() * count(self::TIERS),
            $catalogs->count(),
            $dry ? ' (dry-run)' : ''
        ));

        if ($catalogs->isEmpty()) {
            $this->components->warn('Tidak ada Catalog yang perlu diproses.');

            return;
        }

        $total = $catalogs->count() * count(self::TIERS);
        $done = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::transaction(function () use ($catalogs, $dry, $bar): void {
            foreach ($catalogs as $catalog) {
                $basePrice = (float) $catalog->price;

                foreach (self::TIERS as $sortOrder => $tier) {
                    if (! $dry) {
                        // Upsert on (catalog_id, sort_order) so re-running after a
                        // copy change refreshes names/descriptions in place instead
                        // of piling up duplicate tiers.
                        $this->upsertTier($catalog, $sortOrder, $tier, $basePrice);
                    }

                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
    }

    protected function upsertTier(Catalog $catalog, int $sortOrder, array $tier, float $basePrice): Package
    {
        $price = (int) round($basePrice * $tier['multiplier']);

        $description = $this->describe($tier['desc_id'], $catalog);
        $descriptionEn = $this->describe($tier['desc_en'], $catalog);

        $slug = Str::slug($catalog->slug.'-'.$tier['key']);

        return Package::updateOrCreate(
            ['catalog_id' => $catalog->getKey(), 'sort_order' => $sortOrder],
            [
                'category_id' => $catalog->category_id,
                'slug' => $slug,
                'name' => $tier['name_id'],
                'description' => $description,
                'name_translations' => $this->translations($tier['name_id'], $tier['name_en']),
                'description_translations' => $this->translations($description, $descriptionEn),
                'price' => $price,
                'discount_price' => null,
                'stock' => 10,
                'features' => $tier['features_id'],
                'is_active' => true,
                'is_featured' => false,
            ]
        );
    }

    /**
     * Fill `{name}` / `{category}` so no two catalogs end up with the same copy.
     */
    protected function describe(string $template, Catalog $catalog): string
    {
        $categoryName = $catalog->category?->name;

        if ($categoryName === null || $categoryName === '') {
            $categoryName = 'dekorasi bunga';
        }

        return strtr($template, [
            '{name}' => $catalog->name,
            '{category}' => $categoryName,
        ]);
    }

    /**
     * Flat id/en map, matching the shape the other seeders write into
     * `name_translations` / `description_translations`.
     *
     * @return array<string, string>
     */
    protected function translations(string $id, string $en): array
    {
        return ['id' => $id, 'en' => $en];
    }
}
