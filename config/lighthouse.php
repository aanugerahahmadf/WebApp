<?php

use GraphQL\Error\DebugFlag;
use Nuwave\Lighthouse\Execution\AuthenticationErrorHandler;
use Nuwave\Lighthouse\Execution\AuthorizationErrorHandler;
use Nuwave\Lighthouse\Execution\ReportingErrorHandler;
use Nuwave\Lighthouse\Execution\ValidationErrorHandler;

/*
|--------------------------------------------------------------------------
| Lighthouse (GraphQL) Configuration
|--------------------------------------------------------------------------
|
| Targets the installed nuwave/lighthouse v6 API. Debug flags come from
| GraphQL\Error\DebugFlag (webonyx/graphql-php) and the error handler list
| only contains classes that actually exist in v6.
|
*/

return [
    'route' => [
        'uri' => '/graphql',
        'middleware' => [
            'api',
            \App\Http\Middleware\GraphQLMiddleware::class,
        ],
    ],

    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    'namespaces' => [
        'models' => [
            'App\\Models',
            'App\\Models\\User',
            'App\\Models\\Catalog',
            'App\\Models\\Category',
            'App\\Models\\Package',
            'App\\Models\\Order',
            'App\\Models\\Cart',
            'App\\Models\\Wishlist',
            'App\\Models\\Review',
            'App\\Models\\Voucher',
            'App\\Models\\Cbir',
            'App\\Models\\Search',
            'App\\Models\\Home',
            'App\\Models\\History',
            'App\\Models\\Message',
            'App\\Models\\Transaction',
        ],
        'queries' => ['App\\GraphQL\\Queries', 'App\\Http\\GraphQL\\Resolvers'],
        'mutations' => ['App\\GraphQL\\Mutations', 'App\\Http\\GraphQL\\Resolvers'],
        'subscriptions' => ['App\\GraphQL\\Subscriptions'],
        'interfaces' => ['App\\GraphQL\\Interfaces'],
        'unions' => ['App\\GraphQL\\Unions'],
        'scalars' => [
            'App\\GraphQL\\Scalars',
            'Nuwave\\Lighthouse\\Schema\\Types\\Scalars',
        ],
        'directives' => ['App\\GraphQL\\Directives'],
        'validators' => ['App\\GraphQL\\Validators'],
    ],

    'security' => [
        'max_query_complexity' => 1000,
        'max_query_depth' => 10,
        'disable_introspection' => env('APP_ENV') === 'production' ? 1 : 0,
    ],

    'pagination' => [
        'default_count' => 10,
        'max_count' => 100,
    ],

    // Debugging is only applied when the global Laravel debug config is true.
    // Never expose stack traces, SQL, file paths, or exception details in a
    // production GraphQL response, even if APP_DEBUG is misconfigured.
    'debug' => env('APP_ENV') === 'production'
        ? DebugFlag::NONE
        : (env('LIGHTHOUSE_DEBUG', DebugFlag::INCLUDE_DEBUG_MESSAGE)),

    'error_handlers' => [
        AuthenticationErrorHandler::class,
        AuthorizationErrorHandler::class,
        ValidationErrorHandler::class,
        ReportingErrorHandler::class,
    ],
];
