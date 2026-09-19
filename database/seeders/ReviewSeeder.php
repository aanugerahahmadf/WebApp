<?php

namespace Database\Seeders;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $testUsers = [];
        $names = ['Andi', 'Sari', 'Budi', 'Dewi', 'Rudi', 'Maya', 'Agus', 'Rina'];
        foreach ($names as $name) {
            $testUsers[] = User::firstOrCreate(
                ['email' => strtolower($name).'@reviewer.test'],
                [
                    'identity_type' => 'ktp',
                    'full_name' => $name,
                    'first_name' => $name,
                    'last_name' => 'User',
                    'username' => strtolower($name).'_reviewer',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'whatsapp' => '628'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                ]
            );
        }

        $comments = [
            5 => ['Luar biasa! Sangat memuaskan', 'Kualitas terbaik, recommended!', 'Sempurna untuk acara saya'],
            4 => ['Bagus, sesuai ekspektasi', 'Cukup memuaskan', 'Recommended untuk wedding'],
            3 => ['Cukup baik, ada sedikit kekurangan', 'Standard saja', 'Bisa diterima'],
            2 => ['Kurang memuaskan', 'Tidak sesuai harapan', 'Masih perlu perbaikan'],
            1 => ['Sangat mengecewakan', 'Tidak recommended', 'Buruk sekali'],
        ];

        $packages = Package::all();
        foreach ($packages as $package) {
            $numReviews = random_int(1, 5);
            $selectedUsers = collect($testUsers)->random(min($numReviews, count($testUsers)));
            foreach ($selectedUsers as $user) {
                $rating = random_int(3, 5);
                $comment = $comments[$rating][array_rand($comments[$rating])];
                $photos = $this->copyReviewPhotos($package);
                Review::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'product_id' => null,
                    'rating' => $rating,
                    'comment' => $comment,
                    'photo' => $photos[0] ?? null,
                    'photos' => $photos,
                ]);
            }
        }

        $products = Product::all();
        foreach ($products as $product) {
            $numReviews = random_int(1, 5);
            $selectedUsers = collect($testUsers)->random(min($numReviews, count($testUsers)));
            foreach ($selectedUsers as $user) {
                $rating = random_int(3, 5);
                $comment = $comments[$rating][array_rand($comments[$rating])];
                $photos = $this->copyReviewPhotos($product);
                Review::create([
                    'user_id' => $user->id,
                    'package_id' => null,
                    'product_id' => $product->id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'photo' => $photos[0] ?? null,
                    'photos' => $photos,
                ]);
            }
        }
    }

    /**
     * Salin beberapa foto item (produk/paket) ke storage publik review-photos.
     *
     * @return array<int, string> Path relatif foto (relatif ke public disk).
     */
    private function copyReviewPhotos(object $item): array
    {
        $source = null;

        if (method_exists($item, 'getFirstMedia')) {
            $media = $item->getFirstMedia('product_image') ?: $item->getFirstMedia('package_image');
            if ($media && method_exists($media, 'getPath') && $media->getPath() && file_exists($media->getPath())) {
                $source = $media->getPath();
            }
        }

        $count = random_int(0, 3);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $rel = $this->copySourceToReviewPhotos($source);
            if ($rel) {
                $result[] = $rel;
            }
        }

        return $result;
    }

    private function copySourceToReviewPhotos(?string $source): ?string
    {
        if (! $source || ! file_exists($source)) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'jpg';
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $ext = 'jpg';
        }

        $destDir = storage_path('app/public/review-photos');
        File::ensureDirectoryExists($destDir);

        $name = 'review-'.Str::random(20).'.'.$ext;
        File::copy($source, $destDir.'/'.$name);

        return 'review-photos/'.$name;
    }
}
