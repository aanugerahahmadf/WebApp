<?php

/**
 * gRPC Server Entry Point
 * 
 * Start the gRPC server:
 * php app/Grpc/server.php
 * 
 * The server listens on port 50051 by default.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Grpc\Server;
use App\Grpc\Services\AuthService;
use App\Grpc\Services\CatalogService;
use App\Grpc\Services\PackageService;
use App\Grpc\Services\OrderService;
use App\Grpc\Services\CartService;
use App\Grpc\Services\WishlistService;
use App\Grpc\Services\ReviewService;
use App\Grpc\Services\VoucherService;
use App\Grpc\Services\CbirService;
use App\Grpc\Services\HomeService;
use App\Grpc\Services\SearchService;

// Bootstrap Laravel app
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$port = env('GRPC_PORT', 50051);

// Create gRPC server
$server = new Server();

// Register services
$authService = new AuthService();
$catalogService = new CatalogService();
$packageService = new PackageService();
$orderService = new OrderService();
$cartService = new CartService();
$wishlistService = new WishlistService();
$reviewService = new ReviewService();
$voucherService = new VoucherService();
$cbirService = new CbirService();
$homeService = new HomeService();
$searchService = new SearchService();

// Handle incoming requests
$server->handle(function ($call, $method) use (
    $authService,
    $catalogService,
    $packageService,
    $orderService,
    $cartService,
    $wishlistService,
    $reviewService,
    $voucherService,
    $cbirService,
    $homeService,
    $searchService
) {
    switch ($method) {
        // Auth Service
        case '/weddingapp.auth.AuthService/Login':
            return $authService->Login($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/Register':
            return $authService->Register($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/Logout':
            return $authService->Logout($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/GetProfile':
            return $authService->GetProfile($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/UpdateProfile':
            return $authService->UpdateProfile($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/ChangePassword':
            return $authService->ChangePassword($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/ForgotPassword':
            return $authService->ForgotPassword($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/ResetPassword':
            return $authService->ResetPassword($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/RefreshToken':
            return $authService->RefreshToken($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/VerifyEmail':
            return $authService->VerifyEmail($call, $call->getRequest());
        case '/weddingapp.auth.AuthService/ResendVerificationEmail':
            return $authService->ResendVerificationEmail($call, $call->getRequest());

        // Catalog Service
        case '/weddingapp.catalog.CatalogService/GetCatalog':
            return $catalogService->GetCatalog($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/ListCatalogs':
            return $catalogService->ListCatalogs($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetCatalogBySlug':
            return $catalogService->GetCatalogBySlug($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetCategories':
            return $catalogService->GetCategories($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetCategory':
            return $catalogService->GetCategory($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetCatalogsByCategory':
            return $catalogService->GetCatalogsByCategory($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/SearchCatalogs':
            return $catalogService->SearchCatalogs($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetFeaturedCatalogs':
            return $catalogService->GetFeaturedCatalogs($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetOnSaleCatalogs':
            return $catalogService->GetOnSaleCatalogs($call, $call->getRequest());
        case '/weddingapp.catalog.CatalogService/GetRelatedCatalogs':
            return $catalogService->GetRelatedCatalogs($call, $call->getRequest());

        // Package Service
        case '/weddingapp.package.PackageService/GetPackage':
            return $packageService->GetPackage($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetPackages':
            return $packageService->GetPackages($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetPackageBySlug':
            return $packageService->GetPackageBySlug($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetFeaturedPackages':
            return $packageService->GetFeaturedPackages($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetOnSalePackages':
            return $packageService->GetOnSalePackages($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetPackagesByCategory':
            return $packageService->GetPackagesByCategory($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetPackagesByCapacity':
            return $packageService->GetPackagesByCapacity($call, $call->getRequest());
        case '/weddingapp.package.PackageService/GetRelatedPackages':
            return $packageService->GetRelatedPackages($call, $call->getRequest());
        case '/weddingapp.package.PackageService/SearchPackages':
            return $packageService->SearchPackages($call, $call->getRequest());

        // Order Service
        case '/weddingapp.order.OrderService/GetOrder':
            return $orderService->GetOrder($call, $call->getRequest());
        case '/weddingapp.order.OrderService/GetOrders':
            return $orderService->GetOrders($call, $call->getRequest());
        case '/weddingapp.order.OrderService/GetMyOrders':
            return $orderService->GetMyOrders($call, $call->getRequest());
        case '/weddingapp.order.OrderService/CreateOrder':
            return $orderService->CreateOrder($call, $call->getRequest());
        case '/weddingapp.order.OrderService/UpdateOrderStatus':
            return $orderService->UpdateOrderStatus($call, $call->getRequest());
        case '/weddingapp.order.OrderService/UpdatePaymentStatus':
            return $orderService->UpdatePaymentStatus($call, $call->getRequest());
        case '/weddingapp.order.OrderService/CancelOrder':
            return $orderService->CancelOrder($call, $call->getRequest());
        case '/weddingapp.order.OrderService/Reorder':
            return $orderService->Reorder($call, $call->getRequest());
        case '/weddingapp.order.OrderService/GetOrderTimeline':
            return $orderService->GetOrderTimeline($call, $call->getRequest());
        case '/weddingapp.order.OrderService/GetOrderStats':
            return $orderService->GetOrderStats($call, $call->getRequest());
        case '/weddingapp.order.OrderService/ProcessPayment':
            return $orderService->ProcessPayment($call, $call->getRequest());
        case '/weddingapp.order.OrderService/RequestRefund':
            return $orderService->RequestRefund($call, $call->getRequest());

        // Cart Service
        case '/weddingapp.cart.CartService/GetCart':
            return $cartService->GetCart($call, $call->getRequest());
        case '/weddingapp.cart.CartService/AddToCart':
            return $cartService->AddToCart($call, $call->getRequest());
        case '/weddingapp.cart.CartService/UpdateCartItem':
            return $cartService->UpdateCartItem($call, $call->getRequest());
        case '/weddingapp.cart.CartService/RemoveFromCart':
            return $cartService->RemoveFromCart($call, $call->getRequest());
        case '/weddingapp.cart.CartService/ClearCart':
            return $cartService->ClearCart($call, $call->getRequest());
        case '/weddingapp.cart.CartService/ApplyVoucher':
            return $cartService->ApplyVoucher($call, $call->getRequest());
        case '/weddingapp.cart.CartService/RemoveVoucher':
            return $cartService->RemoveVoucher($call, $call->getRequest());
        case '/weddingapp.cart.CartService/SyncCart':
            return $cartService->SyncCart($call, $call->getRequest());
        case '/weddingapp.cart.CartService/EstimateShipping':
            return $cartService->EstimateShipping($call, $call->getRequest());

        // Wishlist Service
        case '/weddingapp.wishlist.WishlistService/GetWishlist':
            return $wishlistService->GetWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/ToggleWishlist':
            return $wishlistService->ToggleWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/AddToWishlist':
            return $wishlistService->AddToWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/RemoveFromWishlist':
            return $wishlistService->RemoveFromWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/ClearWishlist':
            return $wishlistService->ClearWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/IsInWishlist':
            return $wishlistService->IsInWishlist($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/MoveToCart':
            return $wishlistService->MoveToCart($call, $call->getRequest());
        case '/weddingapp.wishlist.WishlistService/ShareWishlist':
            return $wishlistService->ShareWishlist($call, $call->getRequest());

        // Review Service
        case '/weddingapp.review.ReviewService/GetReviews':
            return $reviewService->GetReviews($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetCatalogReviews':
            return $reviewService->GetCatalogReviews($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetPackageReviews':
            return $reviewService->GetPackageReviews($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetMyReviews':
            return $reviewService->GetMyReviews($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetReview':
            return $reviewService->GetReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/CreateReview':
            return $reviewService->CreateReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/UpdateReview':
            return $reviewService->UpdateReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/DeleteReview':
            return $reviewService->DeleteReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/AddReviewPhoto':
            return $reviewService->AddReviewPhoto($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/RemoveReviewPhoto':
            return $reviewService->RemoveReviewPhoto($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/MarkHelpful':
            return $reviewService->MarkHelpful($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/UnmarkHelpful':
            return $reviewService->UnmarkHelpful($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetReviewStats':
            return $reviewService->GetReviewStats($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/GetFeaturedReviews':
            return $reviewService->GetFeaturedReviews($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/ModerateReview':
            return $reviewService->ModerateReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/ReplyToReview':
            return $reviewService->ReplyToReview($call, $call->getRequest());
        case '/weddingapp.review.ReviewService/ReportReview':
            return $reviewService->ReportReview($call, $call->getRequest());

        // Voucher Service
        case '/weddingapp.voucher.VoucherService/ValidateVoucher':
            return $voucherService->ValidateVoucher($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/GetVoucher':
            return $voucherService->GetVoucher($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/GetVouchers':
            return $voucherService->GetVouchers($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/GetMyVouchers':
            return $voucherService->GetMyVouchers($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/GetVoucherStats':
            return $voucherService->GetVoucherStats($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/CreateVoucher':
            return $voucherService->CreateVoucher($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/UpdateVoucher':
            return $voucherService->UpdateVoucher($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/DeleteVoucher':
            return $voucherService->DeleteVoucher($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/ToggleVoucherStatus':
            return $voucherService->ToggleVoucherStatus($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/BulkCreateVouchers':
            return $voucherService->BulkCreateVouchers($call, $call->getRequest());
        case '/weddingapp.voucher.VoucherService/ExportVoucherCodes':
            return $voucherService->ExportVoucherCodes($call, $call->getRequest());

        // CBIR Service
        case '/weddingapp.cbir.CbirService/Search':
            return $cbirService->Search($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/GetStats':
            return $cbirService->GetStats($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/AddToIndex':
            return $cbirService->AddToIndex($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/RemoveFromIndex':
            return $cbirService->RemoveFromIndex($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/GetHistory':
            return $cbirService->GetHistory($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/SaveSearch':
            return $cbirService->SaveSearch($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/DeleteSearch':
            return $cbirService->DeleteSearch($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/ClearHistory':
            return $cbirService->ClearHistory($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/SubmitFeedback':
            return $cbirService->SubmitFeedback($call, $call->getRequest());
        case '/weddingapp.cbir.CbirService/RebuildIndex':
            return $cbirService->RebuildIndex($call, $call->getRequest());

        // Home Service
        case '/weddingapp.home.HomeService/GetHomeData':
            return $homeService->GetHomeData($call, $call->getRequest());
        case '/weddingapp.home.HomeService/GetHeroBanners':
            return $homeService->GetHeroBanners($call, $call->getRequest());
        case '/weddingapp.home.HomeService/GetPromotions':
            return $homeService->GetPromotions($call, $call->getRequest());

        // Search Service
        case '/weddingapp.search.SearchService/Search':
            return $searchService->Search($call, $call->getRequest());
        case '/weddingapp.search.SearchService/GetSuggestions':
            return $searchService->GetSuggestions($call, $call->getRequest());
        case '/weddingapp.search.SearchService/GetPopularSearches':
            return $searchService->GetPopularSearches($call, $call->getRequest());
        case '/weddingapp.search.SearchService/GetFilters':
            return $searchService->GetFilters($call, $call->getRequest());

        default:
            $call->sendStatusFromCode(Grpc\Status::UNIMPLEMENTED, 'Method not implemented: ' . $method);
    }
});

echo "gRPC server started on port {$port}\n";
$server->run();