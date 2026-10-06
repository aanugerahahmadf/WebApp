<?php

namespace Database\Seeders;

use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Review\Review;
use App\Models\ReviewVote\ReviewVote;
use App\Models\User\User;
use App\Services\ReviewPhotoStorage\ReviewPhotoStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReviewSeeder extends Seeder
{
    /**
     * Judul + komentar gaya Shopee per rating.
     *
     * @var array<int, array{title: array<int, string>, comment: array<int, string>}>
     */
    private array $texts = [
        5 => [
            'title' => ['Sangat memuaskan!', 'Kualitas terbaik!', 'Sempurna untuk acara saya', 'Melebihi ekspektasi'],
            'comment' => ['Luar biasa! Sangat memuaskan', 'Kualitas terbaik, recommended!', 'Sempurna untuk acara saya', 'Hasilnya rapi banget, tamu sampai tanya vendornya siapa', 'Pengerjaan cepat dan hasilnya mewah'],
        ],
        4 => [
            'title' => ['Bagus, sesuai ekspektasi', 'Puas dengan hasilnya', 'Recommended untuk wedding'],
            'comment' => ['Bagus, sesuai ekspektasi', 'Cukup memuaskan', 'Recommended untuk wedding', 'Overall puas, ada sedikit yang bisa lebih rapi', 'Warnanya cantik, pengiriman tepat waktu'],
        ],
        3 => [
            'title' => ['Cukup baik', 'Lumayan, sesuai harga', 'Bisa diterima'],
            'comment' => ['Cukup baik, ada sedikit kekurangan', 'Standard saja', 'Bisa diterima', 'Lumayan lah dengan harga segini', 'Ada bagian yang kurang rapi tapi masih oke'],
        ],
        2 => [
            'title' => ['Kurang memuaskan', 'Tidak sesuai harapan'],
            'comment' => ['Kurang memuaskan', 'Tidak sesuai harapan', 'Warnanya beda dari foto katalog', 'Pengerjaannya terburu-buru kayaknya'],
        ],
        1 => [
            'title' => ['Sangat mengecewakan', 'Tidak recommended'],
            'comment' => ['Sangat mengecewakan', 'Tidak recommended', 'Buruk sekali', 'Jauh dari ekspektasi, kapok'],
        ],
    ];

    /** Bobot distribusi rating ala Shopee (mayoritas puas, ada yang kecewa). */
    private array $weights = [5 => 45, 4 => 25, 3 => 15, 2 => 10, 1 => 5];

    public function run(): void
    {
        $testUsers = $this->makeTestUsers();

        // Idempoten: hapus ulasan lama milik akun seeder (beserta vote-nya),
        // lalu tanam ulang yang lengkap. Ulasan user asli tidak disentuh.
        $testUserIds = collect($testUsers)->pluck('id')->all();
        $oldReviewIds = Review::query()->whereIn('user_id', $testUserIds)->pluck('id')->all();
        if ($oldReviewIds !== []) {
            ReviewVote::query()->whereIn('review_id', $oldReviewIds)->delete();

            // Baris review lama dihapus lebih dulu, baru file yatim dibersihkan.
            // Urutan ini penting: pruneOrphans() menentukan file yang aman dihapus
            // dari sisa referensi di tabel reviews, jadi harus dijalankan setelah
            // baris lama hilang -- kalau tidak, tidak ada yang jadi yatim.
            Review::query()->whereIn('id', $oldReviewIds)->delete();

            $this->deletePhotoFiles();
        }

        foreach (Package::all() as $package) {
            $this->seedForItem($testUsers, 'package_id', $package->id, $package);
        }

        foreach (Product::all() as $product) {
            $this->seedForItem($testUsers, 'product_id', $product->id, $product);
        }
    }

    /**
     * @return array<int, User>
     */
    private function makeTestUsers(): array
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
                    'password' => Hash::make('@ReviewerPass123'),
                    'email_verified_at' => now(),
                    'whatsapp' => '628'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                ]
            );
        }

        return $testUsers;
    }

    /**
     * @param  array<int, User>  $testUsers
     */
    private function seedForItem(array $testUsers, string $field, int $itemId, object $item): void
    {
        $numReviews = random_int(5, count($testUsers));
        $selectedUsers = collect($testUsers)->shuffle()->take($numReviews);

        foreach ($selectedUsers as $user) {
            $rating = $this->weightedRating();
            $texts = $this->texts[$rating];
            $photos = $this->copyReviewPhotos($item);
            $createdAt = now()->subDays(random_int(0, 90))->subHours(random_int(0, 23))->subMinutes(random_int(0, 59));

            $review = Review::create([
                'user_id' => $user->id,
                $field => $itemId,
                'rating' => $rating,
                'title' => $texts['title'][array_rand($texts['title'])],
                'comment' => $texts['comment'][array_rand($texts['comment'])],
                'photo' => $photos[0] ?? null,
                'photos' => $photos,
                'helpful_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->seedHelpfulVotes($review, $testUsers, $user->id);
        }
    }

    private function weightedRating(): int
    {
        $roll = random_int(1, array_sum($this->weights));
        foreach ($this->weights as $rating => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $rating;
            }
        }

        return 5;
    }

    /**
     * @param  array<int, User>  $testUsers
     */
    private function seedHelpfulVotes(Review $review, array $testUsers, int $authorId): void
    {
        $voters = collect($testUsers)
            ->reject(fn (User $user): bool => $user->id === $authorId)
            ->shuffle()
            ->take(random_int(0, 5));

        foreach ($voters as $voter) {
            ReviewVote::create(['user_id' => $voter->id, 'review_id' => $review->id]);
        }

        if ($voters->isNotEmpty()) {
            $review->update(['helpful_count' => $voters->count()]);
        }
    }

    /**
     * Ambil foto item (produk/paket) untuk storage publik review-photos.
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

        if (! $source) {
            return [];
        }

        // Hanya ada satu sumber foto untuk item ini, jadi cukup satu path.
        // Nama file diturunkan dari hash isi, sehingga foto yang sama tidak
        // pernah tersalin dua kali antar-run seeder.
        $relative = ReviewPhotoStorage::store($source);

        return $relative ? [$relative] : [];
    }

    /**
     * Hapus file foto yang tidak lagi dirujuk review mana pun.
     *
     * Penting: nama file foto berbasis hash isi, jadi satu file bisa dipakai
     * banyak review. Karena itu penghapusan dicek ulang terhadap SELURUH
     * tabel reviews -- bukan hanya review milik seeder -- agar foto milik user
     * asli tidak ikut terhapus. Lihat ReviewPhotoStorage::pruneOrphans().
     */
    private function deletePhotoFiles(): void
    {
        $result = ReviewPhotoStorage::pruneOrphans(apply: true);

        if ($result['deleted'] > 0) {
            $this->command?->info("  {$result['deleted']} foto ulasan yatim dihapus");
        }
    }
}
