<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GraphQLMiddleware
{
    /**
     * Operations that can expose or modify private user data.
     *
     * Public catalog/search operations intentionally remain available to
     * guests. This check is performed before Lighthouse executes resolvers so
     * a forgotten resolver-level check cannot turn into an IDOR/data leak.
     *
     * @var list<string>
     */
    private const PRIVATE_OPERATIONS = [
        'me', 'user', 'users', 'userProfile', 'userActivity', 'myOrders',
        'order', 'orderByNumber', 'orders', 'orderStats', 'orderTimeline',
        'myReviews', 'myVouchers', 'voucherStats', 'myCart', 'myWishlist',
        'isInWishlist', 'cbirStats', 'cbirHistory', 'cbirSavedResults',
        'updateProfile', 'changePassword', 'updateAvatar', 'deleteAvatar',
        'deleteAccount', 'updateNotificationPreferences',
        'updateLocalePreferences', 'addToCart', 'updateCartItem',
        'removeFromCart', 'clearCart', 'applyVoucherToCart',
        'removeVoucherFromCart', 'syncCart', 'estimateShipping',
        'toggleWishlist', 'addToWishlist', 'removeFromWishlist',
        'clearWishlist', 'moveToCart', 'shareWishlist', 'createOrder',
        'cancelOrder', 'reorder', 'updateOrder', 'updateOrderStatus',
        'updatePaymentStatus', 'processPayment', 'requestRefund',
        'myReviews', 'createReview', 'updateReview', 'deleteReview',
        'addReviewPhoto', 'removeReviewPhoto', 'markReviewHelpful',
        'unmarkReviewHelpful', 'saveCbirSearch', 'deleteCbirSearch',
        'clearCbirHistory', 'submitCbirFeedback', 'rebuildCbirIndex',
        'addToCbirIndex', 'removeFromCbirIndex',
    ];
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Do not allow queries in URLs: GET puts credentials, personal data,
        // and query variables into browser/proxy history and access logs.
        // The non-production playground is an HTML page, not a GraphQL
        // operation. Keep it available only when the route itself is enabled;
        // all actual GraphQL GET requests remain forbidden.
        if ($request->isMethod('get') && $request->is('graphql/playground')) {
            return $next($request);
        }

        if ($request->isMethod('get')) {
            return response()->json(['errors' => [['message' => 'GraphQL GET is disabled.']]], 405)
                ->header('Allow', 'POST');
        }

        if ($request->isMethod('options')) {
            return response()->noContent(204);
        }

        $query = (string) $request->input('query', '');
        foreach (self::PRIVATE_OPERATIONS as $operation) {
            if (preg_match('/\\b'.preg_quote($operation, '/').'\\s*(?:\\(|\\{)/', $query) === 1) {
                $user = $request->user('sanctum')
                    ?? Auth::guard('web')->user();

                if ($user === null) {
                    return response()->json([
                        'errors' => [['message' => 'Authentication required.']],
                    ], 401);
                }

                break;
            }
        }

        // Ensure content type is application/json for POST requests
        if ($request->isMethod('post') && !$request->isJson()) {
            // Allow GraphQL queries with application/json content type
            if ($request->header('Content-Type') !== 'application/json') {
                return response()->json([
                    'errors' => [
                        [
                            'message' => 'Content-Type must be application/json',
                        ],
                    ],
                ], 415);
            }
        }

        $response = $next($request);

        $origin = (string) $request->headers->get('Origin');
        $allowedOrigins = array_values(array_filter((array) config('cors.allowed_origins', [])));
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-TOKEN');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
