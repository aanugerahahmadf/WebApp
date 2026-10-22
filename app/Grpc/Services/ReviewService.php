<?php

namespace App\Grpc\Services;

use App\Models\Review\Review;
use App\Models\ReviewVote\ReviewVote;
use App\Models\Review\ReviewReport;
use App\Models\ReviewReply\ReviewReply;
use App\Models\Order\Order;
use Grpc\ServerCall;
use Laravel\Sanctum\PersonalAccessToken;

class ReviewService
{
    public function GetReviews(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Review\ReviewsResponse();
            return $call->sendResponse($response);
        }
        
        $query = Review::with(['user', 'catalog', 'package']);
        
        if ($request->getStatus()) {
            $query->where('status', $request->getStatus());
        }
        if ($request->getRating()) {
            $query->where('rating', $request->getRating());
        }
        
        $perPage = $request->getPerPage() ?: 20;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Review\ReviewsResponse();
        foreach ($paginated->items() as $review) {
            $response->addReviews($this->buildReviewProto($review));
        }
        
        $pagination = new \Weddingapp\Review\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetCatalogReviews(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = Review::with(['user', 'catalog', 'package'])
            ->where('catalog_id', $request->getCatalogId())
            ->where('status', 'approved');
        
        if ($request->getRating()) {
            $query->where('rating', $request->getRating());
        }
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Review\ReviewsResponse();
        foreach ($paginated->items() as $review) {
            $response->addReviews($this->buildReviewProto($review));
        }
        
        $pagination = new \Weddingapp\Review\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetPackageReviews(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = Review::with(['user', 'catalog', 'package'])
            ->where('package_id', $request->getPackageId())
            ->where('status', 'approved');
        
        if ($request->getRating()) {
            $query->where('rating', $request->getRating());
        }
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        $paginated = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Review\ReviewsResponse();
        foreach ($paginated->items() as $review) {
            $response->addReviews($this->buildReviewProto($review));
        }
        
        $pagination = new \Weddingapp\Review\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetMyReviews(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $perPage = $request->getPerPage() ?: 10;
        $page = $request->getPage() ?: 1;
        
        $paginated = Review::with(['user', 'catalog', 'package'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = new \Weddingapp\Review\ReviewsResponse();
        foreach ($paginated->items() as $review) {
            $response->addReviews($this->buildReviewProto($review));
        }
        
        $pagination = new \Weddingapp\Review\PaginationMeta();
        $pagination->setCurrentPage($paginated->currentPage());
        $pagination->setPerPage($paginated->perPage());
        $pagination->setTotal($paginated->total());
        $pagination->setLastPage($paginated->lastPage());
        $pagination->setFrom($paginated->firstItem());
        $pagination->setTo($paginated->lastItem());
        $pagination->setHasNextPage($paginated->hasMorePages());
        $pagination->setHasPrevPage($paginated->currentPage() > 1);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function GetReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::with(['user', 'catalog', 'package', 'replies.user'])
            ->find($request->getId());
        
        $response = new \Weddingapp\Review\ReviewResponse();
        if ($review) {
            $response->setReview($this->buildReviewProto($review));
            $response->setMessage('Review found');
        } else {
            $response->setMessage('Review not found');
        }
        
        return $call->sendResponse($response);
    }

    public function CreateReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $order = Order::where('id', $request->getOrderId())
            ->where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->where('status', 'delivered')
            ->first();
        
        if (!$order) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Order not found or not eligible for review');
            return $call->sendResponse($response);
        }
        
        $existingReview = Review::where('order_id', $request->getOrderId())
            ->where('user_id', $user->id)
            ->first();
        
        if ($existingReview) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('You have already reviewed this order');
            return $call->sendResponse($response);
        }
        
        if (!$request->getCatalogId() && !$request->getPackageId()) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Catalog or Package is required');
            return $call->sendResponse($response);
        }
        
        $review = Review::create([
            'user_id' => $user->id,
            'catalog_id' => $request->getCatalogId() ?: null,
            'package_id' => $request->getPackageId() ?: null,
            'order_id' => $request->getOrderId(),
            'rating' => $request->getRating(),
            'title' => $request->getTitle() ?: null,
            'comment' => $request->getComment() ?: null,
            'photos' => $request->getPhotos(),
            'is_anonymous' => $request->getIsAnonymous(),
            'is_verified_purchase' => true,
            'status' => 'pending',
        ]);
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review));
        $response->setMessage('Review created successfully');
        
        return $call->sendResponse($response);
    }

    public function UpdateReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        if ($review->status !== 'pending' && !$user->hasRole('admin')) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Cannot update moderated review');
            return $call->sendResponse($response);
        }
        
        $updateData = [];
        if ($request->getRating()) $updateData['rating'] = $request->getRating();
        if ($request->getTitle()) $updateData['title'] = $request->getTitle();
        if ($request->getComment()) $updateData['comment'] = $request->getComment();
        if ($request->getPhotos()) $updateData['photos'] = $request->getPhotos();
        if ($request->getIsAnonymous()) $updateData['is_anonymous'] = $request->getIsAnonymous();
        
        $review->update($updateData);
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Review updated successfully');
        
        return $call->sendResponse($response);
    }

    public function DeleteReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getId());
        if (!$review) {
            $response = new \Weddingapp\Review\DeleteReviewResponse();
            $response->setSuccess(false);
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            $response = new \Weddingapp\Review\DeleteReviewResponse();
            $response->setSuccess(false);
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        $review->delete();
        
        $response = new \Weddingapp\Review\DeleteReviewResponse();
        $response->setSuccess(true);
        $response->setMessage('Review deleted successfully');
        
        return $call->sendResponse($response);
    }

    public function AddReviewPhoto(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        $photos = $review->photos ?? [];
        $photos[] = [
            'url' => $request->getUrl(),
            'caption' => $request->getCaption() ?: null,
        ];
        
        $review->photos = $photos;
        $review->save();
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Photo added successfully');
        
        return $call->sendResponse($response);
    }

    public function RemoveReviewPhoto(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        if (!$user->hasRole('admin') && $review->user_id !== $user->id) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Unauthorized');
            return $call->sendResponse($response);
        }
        
        $photos = $review->photos ?? [];
        $photos = array_filter($photos, fn($p) => $p['url'] !== $request->getPhotoUrl());
        
        $review->photos = array_values($photos);
        $review->save();
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Photo removed successfully');
        
        return $call->sendResponse($response);
    }

    public function MarkHelpful(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        $existing = ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$existing) {
            ReviewVote::create([
                'review_id' => $review->id,
                'user_id' => $user->id,
            ]);
            $review->increment('helpful_count');
        }
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Review marked as helpful');
        
        return $call->sendResponse($response);
    }

    public function UnmarkHelpful(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        $existing = ReviewVote::where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();
        
        if ($existing) {
            $existing->delete();
            $review->decrement('helpful_count');
        }
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Review unmarked as helpful');
        
        return $call->sendResponse($response);
    }

    public function GetReviewStats(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $query = Review::query();
        
        if ($request->getCatalogId()) {
            $query->where('catalog_id', $request->getCatalogId());
        }
        if ($request->getPackageId()) {
            $query->where('package_id', $request->getPackageId());
        }
        
        $totalReviews = $query->count();
        $averageRating = $query->avg('rating') ?? 0;
        
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = (clone $query)->where('rating', $i)->count();
            $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0;
            $ratingCount = new \Weddingapp\Review\RatingCount();
            $ratingCount->setRating($i);
            $ratingCount->setCount($count);
            $ratingCount->setPercentage($percentage);
            $ratingDistribution[] = $ratingCount;
        }
        
        $withPhotos = (clone $query)->whereNotNull('photos')->where('photos', '!=', '[]')->count();
        $verifiedPurchases = (clone $query)->where('is_verified_purchase', true)->count();
        
        $stats = new \Weddingapp\Review\ReviewStats();
        $stats->setTotalReviews($totalReviews);
        $stats->setAverageRating(round($averageRating, 1));
        foreach ($ratingDistribution as $rd) {
            $stats->addRatingDistribution($rd);
        }
        $stats->setWithPhotos($withPhotos);
        $stats->setVerifiedPurchases($verifiedPurchases);
        
        $response = new \Weddingapp\Review\ReviewStatsResponse();
        $response->setStats($stats);
        
        return $call->sendResponse($response);
    }

    public function GetFeaturedReviews(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $limit = $request->getLimit() ?: 5;
        
        $reviews = Review::with(['user', 'catalog', 'package'])
            ->where('status', 'approved')
            ->where('rating', '>=', 4)
            ->whereNotNull('photos')
            ->where('photos', '!=', '[]')
            ->latest()
            ->take($limit)
            ->get();
        
        $response = new \Weddingapp\Review\ReviewsResponse();
        foreach ($reviews as $review) {
            $response->addReviews($this->buildReviewProto($review));
        }
        
        $pagination = new \Weddingapp\Review\PaginationMeta();
        $pagination->setCurrentPage(1);
        $pagination->setPerPage($limit);
        $pagination->setTotal($reviews->count());
        $pagination->setLastPage(1);
        $pagination->setFrom(1);
        $pagination->setTo($reviews->count());
        $pagination->setHasNextPage(false);
        $pagination->setHasPrevPage(false);
        $response->setPagination($pagination);
        
        return $call->sendResponse($response);
    }

    public function ModerateReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        $review->status = $this->mapReviewStatus($request->getStatus());
        $review->moderation_note = $request->getModerationNote() ?: null;
        $review->moderated_by = $user->id;
        $review->moderated_at = now();
        $review->save();
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Review moderated successfully');
        
        return $call->sendResponse($response);
    }

    public function ReplyToReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        if (!$user->hasRole('admin')) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Unauthorized: Admin access required');
            return $call->sendResponse($response);
        }
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewResponse();
            $response->setMessage('Review not found');
            return $call->sendResponse($response);
        }
        
        ReviewReply::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'comment' => $request->getReply(),
        ]);
        
        $response = new \Weddingapp\Review\ReviewResponse();
        $response->setReview($this->buildReviewProto($review->fresh()));
        $response->setMessage('Reply added successfully');
        
        return $call->sendResponse($response);
    }

    public function ReportReview(ServerCall $call, $request)
    {
        $user = $this->getUserFromToken($request->getAccessToken());
        
        $review = Review::find($request->getReviewId());
        if (!$review) {
            $response = new \Weddingapp\Review\ReviewReportResponse();
            return $call->sendResponse($response);
        }
        
        $report = ReviewReport::create([
            'review_id' => $review->id,
            'reporter_id' => $user->id,
            'reason' => $this->mapReportReason($request->getReason()),
            'description' => $request->getDescription() ?: null,
            'status' => 'pending',
        ]);
        
        $reportProto = new \Weddingapp\Review\ReviewReport();
        $reportProto->setId($report->id);
        $reportProto->setReviewId($report->review_id);
        $reportProto->setReporterId($report->reporter_id);
        $reportProto->setReason($this->mapReportReason($request->getReason()));
        $reportProto->setDescription($report->description ?? '');
        $reportProto->setStatus('pending');
        $reportProto->setCreatedAt($report->created_at?->toISOString() ?? '');
        
        $response = new \Weddingapp\Review\ReviewReportResponse();
        $response->setReport($reportProto);
        
        return $call->sendResponse($response);
    }

    protected function getUserFromToken(string $token): \App\Models\User\User
    {
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            throw new \Exception('Unauthorized: Invalid token');
        }
        return $accessToken->tokenable;
    }

    protected function buildReviewProto($review): \Weddingapp\Review\Review
    {
        $proto = new \Weddingapp\Review\Review();
        $proto->setId($review->id);
        $proto->setUserId($review->user_id);
        $proto->setCatalogId($review->catalog_id ?? 0);
        $proto->setPackageId($review->package_id ?? 0);
        $proto->setOrderId($review->order_id ?? 0);
        $proto->setRating($review->rating);
        $proto->setTitle($review->title ?? '');
        $proto->setComment($review->comment ?? '');
        $proto->setPhotos($review->photos ?? []);
        $proto->setHelpfulCount($review->helpful_count);
        $proto->setIsAnonymous($review->is_anonymous);
        $proto->setIsVerifiedPurchase($review->is_verified_purchase);
        $proto->setStatus($this->mapReviewStatusProto($review->status));
        $proto->setCreatedAt($review->created_at?->toISOString() ?? '');
        $proto->setUpdatedAt($review->updated_at?->toISOString() ?? '');
        
        if ($review->user) {
            $userProto = new \Weddingapp\Review\User();
            $userProto->setId($review->user->id);
            $userProto->setName($review->user->name);
            $userProto->setEmail($review->user->email);
            $userProto->setAvatar($review->user->avatar ?? '');
            $proto->setUser($userProto);
        }
        
        if ($review->catalog) {
            $catalogProto = new \Weddingapp\Catalog\Catalog();
            $catalogProto->setId($review->catalog->id);
            $catalogProto->setName($review->catalog->name);
            $catalogProto->setSlug($review->catalog->slug);
            $catalogProto->setImageUrl($review->catalog->image_url ?? '');
            $proto->setCatalog($catalogProto);
        }
        
        if ($review->package) {
            $packageProto = new \Weddingapp\Package\Package();
            $packageProto->setId($review->package->id);
            $packageProto->setName($review->package->name);
            $packageProto->setSlug($review->package->slug);
            $packageProto->setImageUrl($review->package->image_url ?? '');
            $proto->setPackage($packageProto);
        }
        
        // `replies` is a hasMany on Review; take the latest one as the reply.
        $latestReply = $review->replies->sortByDesc('created_at')->first();

        if ($latestReply) {
            $replyProto = new \Weddingapp\Review\ReviewReply();
            $replyProto->setId($latestReply->id);
            $replyProto->setReviewId($latestReply->review_id);
            $replyProto->setContent($latestReply->comment);
            $replyProto->setCreatedAt($latestReply->created_at?->toISOString() ?? '');

            if ($latestReply->user) {
                $createdByProto = new \Weddingapp\Review\User();
                $createdByProto->setId($latestReply->user->id);
                $createdByProto->setName($latestReply->user->name);
                $createdByProto->setEmail($latestReply->user->email);
                $createdByProto->setAvatar($latestReply->user->avatar ?? '');
                $replyProto->setCreatedBy($createdByProto);
            }

            $proto->setReply($replyProto);
        }
        
        return $proto;
    }

    protected function mapReviewStatus(string $status): string
    {
        $map = [
            'REVIEW_STATUS_UNSPECIFIED' => 'pending',
            'PENDING' => 'pending',
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'HIDDEN' => 'hidden',
        ];
        return $map[$status] ?? 'pending';
    }

    protected function mapReviewStatusProto(string $status): int
    {
        $map = [
            'pending' => \Weddingapp\Review\ReviewStatus::PENDING,
            'approved' => \Weddingapp\Review\ReviewStatus::APPROVED,
            'rejected' => \Weddingapp\Review\ReviewStatus::REJECTED,
            'hidden' => \Weddingapp\Review\ReviewStatus::HIDDEN,
        ];
        return $map[$status] ?? \Weddingapp\Review\ReviewStatus::PENDING;
    }

    protected function mapReportReason(int $reason): string
    {
        $map = [
            0 => 'other',
            1 => 'spam',
            2 => 'inappropriate',
            3 => 'fake',
            4 => 'offensive',
            5 => 'irrelevant',
            6 => 'other',
        ];
        return $map[$reason] ?? 'other';
    }
}