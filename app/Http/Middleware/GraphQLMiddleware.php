<?php

namespace App\Http\Middleware;

use Closure;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GraphQLMiddleware
{
    /**
     * Root fields that can expose or modify private user data.
     *
     * Public catalog/search operations intentionally remain available to
     * guests. This check is performed before Lighthouse executes resolvers so
     * a forgotten resolver-level check cannot turn into an IDOR/data leak.
     *
     * Only the root selection set is inspected: a guest browsing a catalog
     * detail legitimately selects `reviews { user { name } }`, and matching
     * `user` anywhere in the document would reject every public query that
     * shows a reviewer name.
     *
     * @var list<string>
     */
    private const PRIVATE_OPERATIONS = [
        'me', 'user', 'users', 'userProfile', 'userActivity', 'myOrders',
        'order', 'orderByNumber', 'orders', 'orderStats', 'orderTimeline',
        'myReviews', 'myVouchers', 'voucherStats', 'myCart', 'myFavorite',
        'isInFavorite', 'cbirStats', 'cbirHistory', 'cbirSavedResults',
        'updateProfile', 'changePassword', 'updateAvatar', 'deleteAvatar',
        'deleteAccount', 'updateNotificationPreferences',
        'updateLocalePreferences', 'addToCart', 'updateCartItem',
        'removeFromCart', 'clearCart', 'applyVoucherToCart',
        'removeVoucherFromCart', 'syncCart', 'estimateShipping',
        'toggleFavorite', 'addToFavorite', 'removeFromFavorite',
        'clearFavorite', 'moveToCart', 'shareFavorite', 'createOrder',
        'cancelOrder', 'reorder', 'updateOrder', 'updateOrderStatus',
        'updatePaymentStatus', 'processPayment', 'requestRefund',
        'myReviews', 'createReview', 'updateReview', 'deleteReview',
        'addReviewPhoto', 'removeReviewPhoto', 'markReviewHelpful',
        'unmarkReviewHelpful', 'saveCbirSearch', 'deleteCbirSearch',
        'clearCbirHistory', 'submitCbirFeedback', 'rebuildCbirIndex',
        'addToCbirIndex', 'removeFromCbirIndex',
        // Notification + chat: keduanya membaca dan menulis data pribadi
        // (isi pesan, inbox, perangkat FCM), jadi gate ada di sini juga, bukan
        // hanya di resolver.
        'myNotifications', 'unreadNotificationCount', 'markNotificationAsRead',
        'markAllNotificationsAsRead', 'deleteNotification', 'registerFcmToken',
        'myConversations', 'conversationMessages', 'conversationUnreadCount',
        'sendChatMessage', 'startChatConversation', 'markConversationAsRead',
        'rateChatExperience', 'starChatMessage', 'addChatReaction',
        'deleteChatMessage', 'forwardChatMessage',
    ];

    /**
     * True when any operation in the document selects a private root field,
     * directly or through a fragment spread.
     *
     * A document that cannot be parsed is judged textually instead, so a
     * malformed query never slips past the gate.
     */
    private function selectsPrivateRootField(string $query): bool
    {
        if (trim($query) === '') {
            return false;
        }

        try {
            $document = Parser::parse($query);
        } catch (SyntaxError) {
            return $this->mentionsPrivateOperationTextually($query);
        }

        $fragments = [];
        foreach ($document->definitions as $definition) {
            if ($definition instanceof FragmentDefinitionNode) {
                $fragments[$definition->name->value] = $definition->selectionSet;
            }
        }

        foreach ($document->definitions as $definition) {
            if (! $definition instanceof OperationDefinitionNode) {
                continue;
            }

            if ($this->selectionSetHasPrivateRootField($definition->selectionSet, $fragments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, SelectionSetNode>  $fragments
     */
    private function selectionSetHasPrivateRootField(SelectionSetNode $set, array $fragments, int $depth = 0): bool
    {
        // Fragments may reference each other; the cap keeps a cyclic document
        // from looping forever.
        if ($depth > 10) {
            return false;
        }

        foreach ($set->selections as $selection) {
            if ($selection instanceof FieldNode) {
                if (in_array($selection->name->value, self::PRIVATE_OPERATIONS, true)) {
                    return true;
                }

                continue;
            }

            if ($selection instanceof FragmentSpreadNode) {
                $fragment = $fragments[$selection->name->value] ?? null;

                if ($fragment !== null && $this->selectionSetHasPrivateRootField($fragment, $fragments, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function mentionsPrivateOperationTextually(string $query): bool
    {
        foreach (self::PRIVATE_OPERATIONS as $operation) {
            if (preg_match('/\\b'.preg_quote($operation, '/').'\\s*(?:\\(|\\{)/', $query) === 1) {
                return true;
            }
        }

        return false;
    }

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

        /*
         * Tanpa baris ini, `$context->user()` bernilai null untuk token Bearer.
         * Lighthouse mengambil user dari `$request->user()`, yang memakai guard
         * default `web` (session), sementara mobile mengirim token Sanctum.
         * `shouldUse('sanctum')` membuat guard default menjawab user Sanctum
         * untuk sisa request, sehingga ~100 resolver yang memanggil
         * `$context->user()` dan directive `@auth` ikut pulih.
         */
        $user = $request->user('sanctum') ?? Auth::guard('web')->user();

        if ($request->user('sanctum') !== null) {
            Auth::shouldUse('sanctum');
        }

        if ($this->selectsPrivateRootField($query)) {
            if ($user === null) {
                return response()->json([
                    'errors' => [['message' => 'Authentication required.']],
                ], 401);
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
