<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\Review\Review;
use App\Models\Catalog\Catalog;
use App\Models\Package\Package;
use App\Models\Order\Order;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ReviewResolver
{
    /**
     * Get review statistics
     */
    public function stats($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        if (! $user || ! $user->hasRole('admin')) {
            throw new \Exception('Unauthorized');
        }

        $query = Review::query();
        
        if (isset($args['catalogId'])) {
            $query->where('catalog_id', $args['catalogId']);
        }
        
        if (isset($args['packageId'])) {
            $query->where('package_id', $args['packageId']);
        }
        
        $totalReviews = $query->count();
        $averageRating = $query->avg('rating') ?? 0;
        
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = (clone $query)->where('rating', $i)->count();
            $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0;
            $ratingDistribution[] = [
                'rating' => $i,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }
        
        $withPhotos = (clone $query)->whereNotNull('photos')->where('photos', '!=', '[]')->count();
        $verifiedPurchases = (clone $query)->where('is_verified_purchase', true)->count();
        
        return [
            'totalReviews' => $totalReviews,
            'averageRating' => round($averageRating, 1),
            'ratingDistribution' => $ratingDistribution,
            'withPhotos' => $withPhotos,
            'verifiedPurchases' => $verifiedPurchases,
        ];
    }

    /**
     * Get featured reviews
     */
    public function featured($root, array $args, GraphQLContext $context)
    {
        $first = $args['first'] ?? 5;
        
        return Review::where('status', 'approved')
            ->where('rating', '>=', 4)
            ->whereNotNull('photos')
            ->where('photos', '!=', '[]')
            ->with(['user', 'catalog', 'package'])
            ->latest()
            ->take($first)
            ->get();
    }

    /**
     * Admin: Get all reviews
     */
    public function all($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $query = Review::with(['user', 'catalog', 'package']);
        
        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }
        
        if (isset($args['rating'])) {
            $query->where('rating', $args['rating']);
        }
        
        return $query->latest()->paginate($args['first'] ?? 20);
    }

    /**
     * Create review
     */
    public function store($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login to create review');
        }
        
        $input = $args['input'];
        
        // Validate order exists and belongs to user
        $order = Order::where('id', $input['orderId'])
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->where('status', 'delivered')
            ->first();
        
        if (!$order) {
            throw new \Exception('Order not found or not eligible for review');
        }
        
        // Check if already reviewed
        $existingReview = Review::where('order_id', $input['orderId'])
            ->where('user_id', $user->id)
            ->first();
        
        if ($existingReview) {
            throw new \Exception('You have already reviewed this order');
        }
        
        // Validate catalog or package
        if (!$input['catalogId'] && !$input['packageId']) {
            throw new \Exception('Catalog or Package is required');
        }
        
        $isVerifiedPurchase = true;
        
        $review = Review::create([
            'user_id' => $user->id,
            'catalog_id' => $input['catalogId'],
            'package_id' => $input['packageId'],
            'order_id' => $input['orderId'],
            'rating' => $input['rating'],
            'title' => $input['title'] ?? null,
            'comment' => $input['comment'] ?? null,
            'photos' => $input['photos'] ?? [],
            'is_anonymous' => $input['isAnonymous'] ?? false,
            'is_verified_purchase' => $isVerifiedPurchase,
            'status' => 'pending',
        ]);
        
        return $review->load(['user', 'catalog', 'package']);
    }

    /**
     * Update review
     */
    public function update($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        $input = $args['input'];
        
        // Only allow updates if review is not yet moderated
        if ($review->status !== 'pending' && !$user->hasRole('admin')) {
            throw new \Exception('Cannot update moderated review');
        }
        
        $review->update($input);
        
        return $review->fresh()->load(['user', 'catalog', 'package']);
    }

    /**
     * Delete review
     */
    public function destroy($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        $review->delete();
        
        return true;
    }

    /**
     * Add photo to review
     */
    public function addPhoto($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        $photos = $review->photos ?? [];
        $photos[] = [
            'url' => $args['photo']['url'],
            'caption' => $args['photo']['caption'] ?? null,
        ];
        
        $review->photos = $photos;
        $review->save();
        
        return $review->fresh();
    }

    /**
     * Remove photo from review
     */
    public function removePhoto($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
        
        $photos = $review->photos ?? [];
        $photos = array_filter($photos, fn($p) => $p['url'] !== $args['photoUrl']);
        
        $review->photos = array_values($photos);
        $review->save();
        
        return $review->fresh();
    }

    /**
     * Mark review as helpful
     */
    public function markHelpful($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        // Check if already marked
        $existing = \App\Models\ReviewVote\ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$existing) {
            \App\Models\ReviewVote\ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
            ]);
            
            $review->increment('helpful_count');
        }
        
        return $review->fresh();
    }

    /**
     * Unmark review as helpful
     */
    public function unmarkHelpful($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        $existing = \App\Models\ReviewVote\ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();
        
        if ($existing) {
            $existing->delete();
            $review->decrement('helpful_count');
        }
        
        return $review->fresh();
    }

    /**
     * Admin: Moderate review
     */
    public function moderate($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $review = Review::findOrFail($args['reviewId']);
        $review->status = $args['status'];
        $review->moderation_note = $args['moderationNote'] ?? null;
        $review->moderated_by = $user->id;
        $review->moderated_at = now();
        $review->save();
        
        return $review->fresh()->load(['user', 'catalog', 'package']);
    }

    /**
     * Admin: Reply to review
     */
    public function reply($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        
        if (!$user || !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized: Admin access required');
        }
        
        $review = Review::findOrFail($args['reviewId']);
        
        $reply = \App\Models\ReviewReply\ReviewReply::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'comment' => $args['reply'],
        ]);
        
        return $review->fresh()->load(['user', 'catalog', 'package', 'replies.user']);
    }

    /**
     * Report review
     */
    public function report($root, array $args, GraphQLContext $context)
    {
        $user = $context->user();
        $review = Review::findOrFail($args['reviewId']);
        
        if (!$user) {
            throw new \Exception('Unauthorized: Please login');
        }
        
        $report = \App\Models\Review\ReviewReport::create([
            'review_id' => $review->id,
            'reporter_id' => $user->id,
            'reason' => $args['reason'],
            'description' => $args['description'] ?? null,
            'status' => 'pending',
        ]);
        
        return $report->load(['review', 'reporter', 'reviewedBy']);
    }
}
