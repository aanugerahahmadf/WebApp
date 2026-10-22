<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GraphQL Route
    |--------------------------------------------------------------------------
    |
    | This is the URI endpoint where GraphQL will be available.
    |
    */
    'route' => [
        'uri' => '/graphql',
        'middleware' => ['api'],
        'prefix' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Schema
    |--------------------------------------------------------------------------
    |
    | This is the path to your GraphQL schema file.
    |
    */
    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Controllers
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL controllers.
    |
    */
    'controllers' => 'App\\Http\\GraphQL\\Controllers',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Resolvers
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL resolvers.
    |
    */
    'resolvers' => 'App\\Http\\GraphQL\\Resolvers',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Types
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL types.
    |
    */
    'types' => 'App\\GraphQL\\Types',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Mutations
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL mutations.
    |
    */
    'mutations' => 'App\\GraphQL\\Mutations',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Queries
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL queries.
    |
    */
    'queries' => 'App\\GraphQL\\Queries',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Subscriptions
    |--------------------------------------------------------------------------
    |
    | This is the namespace for your GraphQL subscriptions.
    |
    */
    'subscriptions' => 'App\\GraphQL\\Subscriptions',

    /*
    |--------------------------------------------------------------------------
    | GraphQL Validation
    |--------------------------------------------------------------------------
    |
    | This option controls whether GraphQL validation is enabled.
    |
    */
    'validation' => [
        'enabled' => true,
        'rules' => [
            'query' => 'required|string',
            'variables' => 'nullable|array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Pagination
    |--------------------------------------------------------------------------
    |
    | This option controls the default pagination settings.
    |
    */
    'pagination' => [
        'default_count' => 10,
        'max_count' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Security
    |--------------------------------------------------------------------------
    |
    | This option controls the security settings for GraphQL.
    |
    */
    'security' => [
        'max_query_complexity' => 1000,
        'max_query_depth' => 10,
        'disable_introspection' => env('APP_ENV') === 'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Playground
    |--------------------------------------------------------------------------
    |
    | This option controls whether the GraphQL Playground is enabled.
    |
    */
    'playground' => [
        'enabled' => env('APP_ENV') !== 'production',
        'path' => '/graphql-playground',
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Cache
    |--------------------------------------------------------------------------
    |
    | This option controls the cache settings for GraphQL.
    |
    */
    'cache' => [
        'enabled' => env('APP_ENV') === 'production',
        'ttl' => 3600, // 1 hour
    ],
];