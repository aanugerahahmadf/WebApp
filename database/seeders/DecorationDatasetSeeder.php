<?php

namespace Database\Seeders;

use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\Product\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DecorationDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Seeding Decoration Dataset ---');

        // Define categories
        $categories = [
            'traditional' => 'Traditional',
            'modern' => 'Modern',
            'rustic' => 'Rustic',
            'minimalist' => 'Minimalist',
        ];

        $packageCatModels = [];
        $productCatModels = [];
        foreach ($categories as $slug => $name) {
            $packageCatModels[$slug] = Category::firstOrCreate(
                ['slug' => $slug, 'type' => 'package'],
                ['name' => $name]
            );
            $productCatModels[$slug] = Category::firstOrCreate(
                ['slug' => $slug, 'type' => 'product'],
                ['name' => $name]
            );
        }

        // 3. Define dataset products
        $products = [
            [
                'name' => 'Luxurious Traditional Gebyok',
                'category' => 'traditional',
                'filename' => 'traditional.png',
                'price' => 15000000,
                'description' => 'High-end traditional Indonesian wedding decoration with intricate carvings and fresh flowers.',
            ],
            [
                'name' => 'Modern Crystal Stage',
                'category' => 'modern',
                'filename' => 'modern.png',
                'price' => 20000000,
                'description' => 'Elegant modern decoration featuring crystal chandeliers and minimalist white orchids.',
            ],
            [
                'name' => 'Rustic Sunset Garden',
                'category' => 'rustic',
                'filename' => 'rustic.png',
                'price' => 12000000,
                'description' => 'Warm and intimate rustic decoration with wooden arch and pampas grass.',
            ],
            [
                'name' => 'Minimalist Pastel Arch',
                'category' => 'minimalist',
                'filename' => 'minimalist.png',
                'price' => 8000000,
                'description' => 'Simple and clean minimalist decoration with blush roses and eucalyptus.',
            ],
        ];

        // 4. Create products/packages and register images
        $sourceDir = 'D:/Weeding-Organizer-CBIR/ai_core/data/dataset/decorations/';

        foreach ($products as $data) {
            $slug = Str::slug($data['name']);

            // Seed Product (use product-type category)
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $productCatModels[$data['category']]->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'stock' => 10,
                ]
            );

            // Seed Package (use package-type category)
            $package = Package::updateOrCreate(
                ['slug' => $slug.'-package'],
                [
                    'category_id' => $packageCatModels[$data['category']]->id,
                    'name' => $data['name'].' Package',
                    'description' => 'Full service package for '.$data['name'],
                    'price' => $data['price'] + 5000000,
                    'stock' => 5,
                ]
            );

            // Register distinct images
            $productImage = $sourceDir.'products/'.$data['filename'];
            $packageImage = $sourceDir.'packages/'.$data['filename'];

            // Add to Product (Close-up/Component)
            if (File::exists($productImage)) {
                $product->clearMediaCollection('product_image');
                $product->addMedia($productImage)
                    ->preservingOriginal()
                    ->toMediaCollection('product_image');
                $this->command->line("  <info>✓</info> Product Detail: {$data['name']}");
            }

            // Add to Package (Overall/Full)
            if (File::exists($packageImage)) {
                $package->clearMediaCollection('package_image');
                $package->addMedia($packageImage)
                    ->preservingOriginal()
                    ->toMediaCollection('package_image');
                $this->command->line("  <info>✓</info> Package Overall: {$data['name']} Package");
            }
        }

        $this->command->info('--- Decoration Dataset Seeding Complete ('.count($products).' items) ---');
    }
}
