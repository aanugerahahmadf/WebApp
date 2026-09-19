<?php

namespace App\Http\Controllers\Api\User\ReviewController;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\ReviewReply\ReviewReply;
use App\Models\ReviewVote\ReviewVote;
use App\Services\StorageService\StorageService;
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
            $validatedData = $request->validate([
                'package_id' => 'nullable|exists:packages,id',
                'product_id' => 'nullable|exists:products,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'title' => 'nullable|string|max:255',
                'photo' => 'nullable|file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
                'photos' => 'nullable|array|max:6',
                'photos.*' => 'file|mimes:jpeg,png,jpg,webp,mp4,mov,m4v,webm,3gp|max:51200',
            ]);

            if (! filled($validatedData['package_id'] ?? null) && ! filled($validatedData['product_id'] ?? null)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Pilih paket atau produk terlebih dahulu'),
                ], 422);
            }

            $order = Order::where('user_id', Auth::id())
                ->where(function ($q) use ($validatedData): void {
                    if (filled($validatedData['package_id'] ?? null)) {
                        $q->where('package_id', $validatedData['package_id']);
                    }
                    if (filled($validatedData['product_id'] ?? null)) {
                        $q->orWhere('product_id', $validatedData['product_id']);
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
                    if (filled($validatedData['product_id'] ?? null)) {
                        $q->orWhere('product_id', $validatedData['product_id']);
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

            $review = Review::create([
                'user_id' => Auth::id(),
                'package_id' => $validatedData['package_id'] ?? null,
                'product_id' => $validatedData['product_id'] ?? null,
                'rating' => $validatedData['rating'],
                'title' => $validatedData['title'] ?? null,
                'comment' => $validatedData['comment'] ?? null,
                'photo' => $photoPaths[0] ?? null,
                'photos' => $photoPaths,
            ]);

            $review->load(['user:id,full_name,avatar_url', 'package:id,name', 'product:id,name', 'replies' => function ($q) {
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
                'error' => $e->getMessage(),
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

        if ($request->boolean('with_photo')) {
            $query->where(function ($q) {
                $q->whereNotNull('photo')->orWhereNotNull('photos');
            });
        }
    }

    /**
     * Get reviews for a specific package
     */
    public function getPackageReviews($packageId, Request $request)
    {
        try {
            $query = Review::with([
                'user:id,full_name,avatar_url',
                'replies' => function ($q) {
                    $q->with('user:id,full_name,avatar_url');
                },
            ])
                ->where('package_id', $packageId);

            $this->applyFilters($query, $request);
            $this->applySort($query, $request);

            $userId = Auth::id();
            $reviews = $query->paginate($request->get('per_page', 10));

            $items = $reviews->getCollection()->map(function ($review) use ($userId) {
                $review->is_voted = $review->isVotedBy($userId);

                return $review;
            });

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'has_more_pages' => $reviews->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviews for a specific product
     */
    public function getProductReviews($productId, Request $request)
    {
        try {
            $query = Review::with([
                'user:id,full_name,avatar_url',
                'replies' => function ($q) {
                    $q->with('user:id,full_name,avatar_url');
                },
            ])
                ->where('product_id', $productId);

            $this->applyFilters($query, $request);
            $this->applySort($query, $request);

            $userId = Auth::id();
            $reviews = $query->paginate($request->get('per_page', 10));

            $items = $reviews->getCollection()->map(function ($review) use ($userId) {
                $review->is_voted = $review->isVotedBy($userId);

                return $review;
            });

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'has_more_pages' => $reviews->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan'),
                'error' => $e->getMessage(),
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
     * Get rating summary for a product
     */
    public function getProductRatingSummary($productId)
    {
        return $this->ratingSummary('product_id', $productId);
    }

    private function ratingSummary(string $column, $id)
    {
        try {
            $total = Review::where($column, $id)->count();
            $avg = Review::where($column, $id)->avg('rating');

            $distribution = [];
            for ($i = 5; $i >= 1; $i--) {
                $count = Review::where($column, $id)->where('rating', $i)->count();
                $distribution[] = [
                    'star' => $i,
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            }

            $withPhotoCount = Review::where($column, $id)
                ->where(function ($q) {
                    $q->whereNotNull('photo')->orWhereNotNull('photos');
                })
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'average' => $avg ? round((float) $avg, 1) : 0,
                    'total' => $total,
                    'with_photo_count' => $withPhotoCount,
                    'distribution' => $distribution,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ringkasan ulasan'),
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
            $isAdmin = $user->roles()->whereIn('name', ['super_admin', 'admin'])->exists();
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
                'error' => $e->getMessage(),
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
            $isAdmin = $user->roles()->whereIn('name', ['super_admin', 'admin'])->exists();
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviews for a specific organizer
     */
    public function getOrganizerReviews($organizerId, Request $request)
    {
        try {
            $query = Review::with(['user:id,full_name,avatar_url', 'package:id,name,name_translations', 'replies' => function ($q) {
                $q->with('user:id,full_name,avatar_url');
            }])
                ->join('packages', 'reviews.package_id', '=', 'packages.id')
                ->where('packages.vendor_id', $organizerId)
                ->select('reviews.*');

            $this->applyFilters($query, $request);
            $this->applySort($query, $request);

            $userId = Auth::id();
            $reviews = $query->paginate($request->get('per_page', 10));

            $items = $reviews->getCollection()->map(function ($review) use ($userId) {
                $review->is_voted = $review->isVotedBy($userId);

                return $review;
            });

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'has_more_pages' => $reviews->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan organizer'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's own reviews
     */
    public function getUserReviews(Request $request)
    {
        try {
            $query = Review::with(['package.media', 'product.media', 'user:id,full_name,avatar_url', 'replies' => function ($q) {
                $q->with('user:id,full_name,avatar_url');
            }])
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at');

            $reviews = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'has_more_pages' => $reviews->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan Anda'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviews by a specific user (public)
     */
    public function getUserPublicReviews($userId, Request $request)
    {
        try {
            $query = Review::with([
                'user:id,full_name,avatar_url',
                'package:id,name,name_translations',
                'product:id,name,name_translations',
                'replies' => function ($q) {
                    $q->with('user:id,full_name,avatar_url');
                },
            ])
                ->where('user_id', $userId)
                ->orderByDesc('created_at');

            $reviews = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'has_more_pages' => $reviews->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Gagal mengambil ulasan pengguna'),
                'error' => $e->getMessage(),
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
            $review->load(['user:id,full_name,avatar_url', 'package:id,name', 'replies' => function ($q) {
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
