<?php

namespace App\Http\Controllers\Api\User\ReviewController;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\ReviewReply\ReviewReply;
use App\Models\ReviewVote\ReviewVote;
use App\Services\StorageService\StorageService;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Store a new review
     */
    public function store(Request $request)
    {
        try {
            if ($request->filled('tier_id') && ! $request->filled('package_id')) {
                $request->merge(['package_id' => $request->input('tier_id')]);
            }
            $validatedData = $request->validate([
                'package_id' => 'nullable|exists:packages,id',
                'catalog_id' => 'nullable|exists:catalogs,id',
                'order_id' => 'nullable|exists:orders,id',
                'variation' => 'nullable|string|max:255',
                'specifications' => 'nullable|array',
                'is_anonymous' => 'nullable|boolean',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'title' => 'nullable|string|max:255',
                'photo' => 'nullable|file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
                'photos' => 'nullable|array|max:6',
                'photos.*' => 'file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
            ]);

            if (! filled($validatedData['package_id'] ?? null) && ! filled($validatedData['catalog_id'] ?? null)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau katalog terlebih dahulu'),
                ], 422);
            }

            $order = Order::where('user_id', Auth::id())
                ->when(
                    filled($validatedData['order_id'] ?? null),
                    fn ($q) => $q->whereKey($validatedData['order_id'])
                )
                ->where(function ($q) use ($validatedData): void {
                    if (filled($validatedData['package_id'] ?? null)) {
                        $q->where('package_id', $validatedData['package_id']);
                    }
                    if (filled($validatedData['catalog_id'] ?? null)) {
                        $q->orWhere('catalog_id', $validatedData['catalog_id']);
                    }
                })
                ->where('status', 'completed')
                ->first(['*']);

            if (! $order) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Anda hanya dapat memberikan ulasan untuk paket yang telah Anda pesan dan selesai'),
                ], 403);
            }

            $existingReview = Review::where('user_id', Auth::id())
                ->where(function ($q) use ($validatedData): void {
                    if (filled($validatedData['package_id'] ?? null)) {
                        $q->where('package_id', $validatedData['package_id']);
                    }
                    if (filled($validatedData['catalog_id'] ?? null)) {
                        $q->orWhere('catalog_id', $validatedData['catalog_id']);
                    }
                })
                ->first(['*']);

            if ($existingReview) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Anda sudah memberikan ulasan untuk paket ini'),
                ], 409);
            }

            $photoPaths = [];
            if ($request->hasFile('photo')) {
                $photoPaths[] = StorageService::upload($request->file('photo'), 'review-photos');
            }
            if ($request->hasFile('photos')) {
                foreach ((array) $request->file('photos') as $p) {
                    $photoPaths[] = StorageService::upload($p, 'review-photos');
                }
            }

            $variationName = $validatedData['variation'] ?? null;
            if (! $variationName && $order && $order->package) {
                $variationName = $order->package->name;
            }

            $review = Review::create([
                'user_id' => Auth::id(),
                'order_id' => $validatedData['order_id'] ?? $order->id,
                'package_id' => $validatedData['package_id'] ?? null,
                'catalog_id' => $validatedData['catalog_id'] ?? null,
                'rating' => $validatedData['rating'],
                'title' => $validatedData['title'] ?? null,
                'comment' => $validatedData['comment'] ?? null,
                'variation' => $variationName,
                'specifications' => $validatedData['specifications'] ?? null,
                'is_anonymous' => (bool) ($validatedData['is_anonymous'] ?? false),
                'photo' => $photoPaths[0] ?? null,
                'photos' => $photoPaths,
            ]);

            $review->load(['user:id,full_name,avatar_url', 'package:id,name', 'catalog:id,name', 'replies' => function ($q) {
                $q->with('user:id,full_name,avatar_url');
            }]);

            return response()->json([
                'status' => 'success',
                'message' => __('Ulasan berhasil dikirim'),
                'data' => $review,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengirim ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Apply sorting to a review query.
     */
    private function applySort($query, Request $request): void
    {
        $sort = $request->get('sort', 'newest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'highest':
                $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'lowest':
                $query->orderBy('rating', 'asc')->orderBy('created_at', 'desc');
                break;
            case 'most_helpful':
                $query->orderBy('helpful_count', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $query->orderByDesc('created_at');
                break;
        }
    }

    /**
     * Apply filters (rating, with_photo) to a review query.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        if ($request->boolean('with_photo') || $request->boolean('with_media')) {
            $query->where(function ($q) {
                $q->whereNotNull('photo')->where('photo', '<>', '')
                    ->orWhereJsonLength('photos', '>', 0);
            });
        }

        if ($request->boolean('with_comment')) {
            $query->whereNotNull('comment')->where('comment', '<>', '');
        }
    }

    /**
     * Query dasar semua daftar ulasan.
     *
     * Relasi yang dipakai accessor dimuat di sini supaya serialisasi tidak
     * memicu N+1.
     */
    private function baseQuery()
    {
        return Review::query()->with([
            'user:id,full_name,username,avatar_url',
            'catalog:id,name,name_translations',
            'catalog.media',
            'package:id,name,name_translations',
            'package.media',
            'replies' => function ($q) {
                $q->with('user:id,full_name,username,avatar_url');
            },
        ]);
    }

    /**
     * Filter konteks: pencarian dan item tertentu. Summary memakai filter ini
     * supaya angka rata-rata mengikuti konteks halaman.
     */
    private function applyContextFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $query->search((string) $request->input('search'));
        }

        if ($request->filled('catalog_id')) {
            $query->where('catalog_id', $request->input('catalog_id'));
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->input('package_id'));
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('order_id');
        }
    }

    /**
     * Filter yang mempersempit daftar tanpa mengubah summary.
     */
    private function applyNarrowFilters($query, Request $request): void
    {
        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->input('min_rating'));
        }

        if ($request->boolean('with_photo') || $request->boolean('with_media')) {
            $query->withMedia();
        }

        if ($request->boolean('with_comment')) {
            $query->withComment();
        }
    }

    /**
     * Ringkasan rating untuk query apa pun.
     */
    private function summaryForQuery($query): array
    {
        $total = (clone $query)->count();
        $avg = (clone $query)->avg('rating');

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = (clone $query)->where('rating', $i)->count();
            $distribution[] = [
                'star' => $i,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        $withPhotoCount = (clone $query)->withMedia()->count();

        return [
            'average' => $avg ? round((float) $avg, 1) : 0,
            'total' => $total,
            'with_photo_count' => $withPhotoCount,
            'with_media_count' => $withPhotoCount,
            'with_comment_count' => (clone $query)->withComment()->count(),
            'verified_count' => (clone $query)->whereNotNull('order_id')->count(),
            'distribution' => $distribution,
        ];
    }

    /**
     * Payload ulasan yang aman untuk daftar publik.
     *
     * Ulasan anonim tidak membocorkan user_id/relasi user; nama tersamar tetap
     * tampil seperti di Shopee.
     */
    private function serializeReview(Review $review, ?int $viewerId, bool $anonymize = true): array
    {
        $review->loadMissing([
            'user:id,full_name,username,avatar_url',
            'catalog:id,name,name_translations',
            'catalog.media',
            'package:id,name,name_translations',
            'package.media',
            'replies.user:id,full_name,username,avatar_url',
        ]);

        $data = $review->toArray();
        $isAnonymous = $anonymize && (bool) $review->is_anonymous;
        $actualName = $review->user?->full_name
            ?? $review->user?->username
            ?? __('Pengguna');

        $data['reviewer_name'] = $isAnonymous ? $review->masked_username : $actualName;
        $data['reviewer_avatar'] = $isAnonymous ? null : $review->user?->avatar_url;
        $data['is_verified_purchase'] = $review->is_verified_purchase;
        $data['is_voted'] = $review->isVotedBy($viewerId);
        $data['item_name'] = $review->item_name;
        $data['item_image_url'] = $review->item_image_url;

        if ($isAnonymous) {
            $data['user'] = null;
            $data['user_id'] = null;
        }

        return $data;
    }

    /**
     * Payload pagination yang konsisten di semua endpoint daftar.
     */
    private function paginationPayload($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    /**
     * Daftar ulasan dari semua pengguna (kartu + modal "Komentar Penilaian Katalog").
     *
     * `search` tetap dilayani untuk kompatibilitas, tapi tidak lagi dipakai UI
     * mana pun: Beranda dan sheet katalog menyaring lewat chip, bukan teks bebas.
     *
     * Query params: search, catalog_id, package_id, rating, min_rating,
     * with_photo, with_comment, verified_only, sort, page, per_page.
     */
    public function getAllReviews(Request $request)
    {
        try {
            $context = $this->baseQuery();
            $this->applyContextFilters($context, $request);
            $summary = $this->summaryForQuery($context);

            $query = $this->baseQuery();
            $this->applyContextFilters($query, $request);
            $this->applyNarrowFilters($query, $request);
            $this->applySort($query, $request);

            $perPage = min(50, max(1, (int) $request->get('per_page', 10)));
            $paginator = $query->paginate($perPage);
            $userId = Auth::id();

            $items = collect($paginator->items())
                ->map(fn (Review $review): array => $this->serializeReview($review, $userId))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'summary' => $summary,
                'pagination' => $this->paginationPayload($paginator),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan pengguna'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Ringkasan rating semua ulasan (tanpa filter rating).
     */
    public function getAllRatingSummary(Request $request)
    {
        try {
            $context = $this->baseQuery();
            $this->applyContextFilters($context, $request);

            return response()->json([
                'status' => 'success',
                'data' => $this->summaryForQuery($context),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ringkasan ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get reviews for a specific package
     */
    public function getPackageReviews($packageId, Request $request)
    {
        try {
            $context = $this->baseQuery()->where('package_id', $packageId);
            $summary = $this->summaryForQuery($context);

            $query = $this->baseQuery()->where('package_id', $packageId);
            $this->applyNarrowFilters($query, $request);
            $this->applySort($query, $request);

            $reviews = $query->paginate($request->get('per_page', 10));
            $userId = Auth::id();

            $items = collect($reviews->items())
                ->map(fn (Review $review): array => $this->serializeReview($review, $userId))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'summary' => $summary,
                'pagination' => $this->paginationPayload($reviews),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get reviews for a specific catalog
     */
    public function getCatalogReviews($catalogId, Request $request)
    {
        try {
            $context = $this->baseQuery()->where('catalog_id', $catalogId);
            $summary = $this->summaryForQuery($context);

            $query = $this->baseQuery()->where('catalog_id', $catalogId);
            $this->applyNarrowFilters($query, $request);
            $this->applySort($query, $request);

            $reviews = $query->paginate($request->get('per_page', 10));
            $userId = Auth::id();

            $items = collect($reviews->items())
                ->map(fn (Review $review): array => $this->serializeReview($review, $userId))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'summary' => $summary,
                'pagination' => $this->paginationPayload($reviews),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get rating summary for a package
     */
    public function getPackageRatingSummary($packageId)
    {
        return $this->ratingSummary('package_id', $packageId);
    }

    /**
     * Get rating summary for a catalog
     */
    public function getCatalogRatingSummary($catalogId)
    {
        return $this->ratingSummary('catalog_id', $catalogId);
    }

    private function ratingSummary(string $column, $id)
    {
        try {
            $summary = $this->summaryForQuery(
                Review::query()->where($column, $id)
            );

            return response()->json([
                'status' => 'success',
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ringkasan ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Toggle helpful vote on a review
     */
    public function voteHelpful($id)
    {
        try {
            $review = Review::findOrFail($id);
            $userId = Auth::id();

            $existing = ReviewVote::where('user_id', $userId)->where('review_id', $id)->first();

            if ($existing) {
                $existing->delete();
                $review->decrement('helpful_count');
                $voted = false;
            } else {
                ReviewVote::create(['user_id' => $userId, 'review_id' => $id]);
                $review->increment('helpful_count');
                $voted = true;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_voted' => $voted,
                    'helpful_count' => $review->fresh()->helpful_count,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Ulasan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memproses vote'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Admin/superadmin reply to a review
     */
    public function reply(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $isAdmin = ChatStaff::isStaff($user);
            if (! $isAdmin) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Hanya admin yang dapat membalas ulasan'),
                ], 403);
            }

            $review = Review::findOrFail($id);

            $validatedData = $request->validate([
                'comment' => 'required|string|max:1000',
            ]);

            $reply = ReviewReply::create([
                'review_id' => $id,
                'user_id' => Auth::id(),
                'comment' => $validatedData['comment'],
            ]);

            $reply->load('user:id,full_name,avatar_url');

            return response()->json([
                'status' => 'success',
                'message' => __('Balasan berhasil dikirim'),
                'data' => $reply,
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Ulasan tidak ditemukan'),
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengirim balasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Delete a review reply (admin only)
     */
    public function deleteReply($reviewId, $replyId)
    {
        try {
            $user = Auth::user();
            $isAdmin = ChatStaff::isStaff($user);
            if (! $isAdmin) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Hanya admin yang dapat menghapus balasan'),
                ], 403);
            }

            $reply = ReviewReply::where('review_id', $reviewId)->where('id', $replyId)->firstOrFail();
            $reply->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Balasan berhasil dihapus'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Balasan tidak ditemukan'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus balasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get user's own reviews
     */
    public function getUserReviews(Request $request)
    {
        try {
            $userId = (int) Auth::id();

            $context = $this->baseQuery()->forUser($userId);
            $this->applyContextFilters($context, $request);
            $summary = $this->summaryForQuery($context);

            $query = $this->baseQuery()->forUser($userId);
            $this->applyContextFilters($query, $request);
            $this->applyNarrowFilters($query, $request);
            $this->applySort($query, $request);

            $perPage = min(50, max(1, (int) $request->get('per_page', 10)));
            $reviews = $query->paginate($perPage);

            $items = collect($reviews->items())
                // Pemilik ulasan tetap melihat namanya sendiri, tetapi badge
                // "anonim" tetap dikirim lewat flag is_anonymous.
                ->map(fn (Review $review): array => $this->serializeReview($review, $userId, false))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'summary' => $summary,
                'pagination' => $this->paginationPayload($reviews),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan Anda'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Get reviews by a specific user (public)
     */
    public function getUserPublicReviews($userId, Request $request)
    {
        try {
            $viewerId = Auth::id();
            $targetId = (int) $userId;

            $context = $this->baseQuery()->forUser($targetId);
            $summary = $this->summaryForQuery($context);

            $query = $this->baseQuery()->forUser($targetId);
            $this->applyNarrowFilters($query, $request);
            $this->applySort($query, $request);

            $reviews = $query->paginate($request->get('per_page', 10));

            $items = collect($reviews->items())
                ->map(fn (Review $review): array => $this->serializeReview(
                    $review,
                    $viewerId,
                    $viewerId === null || (int) $viewerId !== $targetId,
                ))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'summary' => $summary,
                'pagination' => $this->paginationPayload($reviews),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan pengguna'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $id)
    {
        try {
            $review = Review::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            $validatedData = $request->validate([
                'rating' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'sometimes|nullable|string|max:1000',
                'title' => 'sometimes|nullable|string|max:255',
                'is_anonymous' => 'sometimes|boolean',
                'photo' => 'nullable|file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
                'photos' => 'nullable|array|max:6',
                'photos.*' => 'file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
                'removed_photo_urls' => 'nullable|array',
                'removed_photo_urls.*' => 'string',
            ]);

            $removedUrls = $request->input('removed_photo_urls', []) ?? [];
            $newPhotoPaths = [];
            if ($request->hasFile('photo')) {
                $newPhotoPaths[] = StorageService::upload($request->file('photo'), 'review-photos');
            }
            if ($request->hasFile('photos')) {
                foreach ((array) $request->file('photos') as $p) {
                    $newPhotoPaths[] = StorageService::upload($p, 'review-photos');
                }
            }

            $currentPhoto = $review->photo;
            $currentPhotos = $review->photos ?? [];

            if (! empty($removedUrls)) {
                foreach ($removedUrls as $url) {
                    $path = parse_url($url, PHP_URL_PATH);
                    if ($path) {
                        $relative = ltrim($path, '/');
                        if (str_starts_with($relative, 'storage/')) {
                            $relative = substr($relative, 8);
                        }
                        if ($currentPhoto && str_contains($currentPhoto, $relative)) {
                            StorageService::delete($currentPhoto);
                            $currentPhoto = null;
                        }
                        foreach ($currentPhotos as $idx => $cp) {
                            if ($cp && str_contains($cp, $relative)) {
                                StorageService::delete($cp);
                                unset($currentPhotos[$idx]);
                            }
                        }
                    }
                }
                $currentPhotos = array_values($currentPhotos);
            }

            if ($newPhotoPaths) {
                foreach (array_merge([$currentPhoto], $currentPhotos) as $old) {
                    if ($old) {
                        StorageService::delete($old);
                    }
                }
                $validatedData['photo'] = $newPhotoPaths[0];
                $validatedData['photos'] = $newPhotoPaths;
            } else {
                if ($currentPhoto !== $review->photo || $currentPhotos !== ($review->photos ?? [])) {
                    $validatedData['photo'] = $currentPhoto;
                    $validatedData['photos'] = $currentPhotos;
                }
            }

            $review->update($validatedData);
            $review->load(['user:id,full_name,avatar_url', 'package:id,name', 'catalog:id,name', 'replies' => function ($q) {
                $q->with('user:id,full_name,avatar_url');
            }]);

            return response()->json([
                'status' => 'success',
                'message' => __('Ulasan berhasil diperbarui'),
                'data' => $review,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Validasi gagal'),
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Ulasan tidak ditemukan atau bukan milik Anda'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal memperbarui ulasan'),
                'error' => null,
            ], 500);
        }
    }

    /**
     * Delete a review
     */
    public function destroy($id)
    {
        try {
            $review = Review::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail(['*']);

            foreach (array_merge([$review->photo], $review->photos ?: []) as $old) {
                if ($old) {
                    StorageService::delete($old);
                }
            }

            $review->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('Ulasan berhasil dihapus'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Ulasan tidak ditemukan atau bukan milik Anda'),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal menghapus ulasan'),
                'error' => null,
            ], 500);
        }
    }
}
