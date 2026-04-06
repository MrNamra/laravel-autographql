<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GraphQL Endpoint
    |--------------------------------------------------------------------------
    | The URI where the GraphQL API will be available.
    | Default: '/graphql'
    */
    'endpoint' => env('GRAPHQL_ENDPOINT', '/graphql'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix to Scan
    |--------------------------------------------------------------------------
    | AutoGraphQL will scan routes starting with this prefix to build the schema.
    | Default: 'api' (scans routes like /api/users, /api/posts, etc.)
    */
    'route_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Exclude Specific Routes
    |--------------------------------------------------------------------------
    | List exact route URIs you want to exclude from the GraphQL schema.
    | Example: ['api/admin/logs', 'api/internal/stats']
    */
    'exclude_routes' => [],

    /*
    |--------------------------------------------------------------------------
    | GraphiQL Playground
    |--------------------------------------------------------------------------
    | The interactive GraphiQL IDE at /graphiql.
    | By default, this is only enabled when APP_DEBUG is true.
    */
    'playground' => [
        'enabled' => env('GRAPHQL_PLAYGROUND', env('APP_DEBUG', false)),
        
        /*
        | Set this to true to require a valid session/auth context even in dev.
        | If false, it follows the 'enabled' setting above.
        */
        'require_auth' => env('GRAPHQL_PLAYGROUND_AUTH', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Safety & Permissions
    |--------------------------------------------------------------------------
    | Control access to the GraphQL endpoint in production environments.
    */
    'production' => [
        /*
        | If true, the GraphQL endpoint remains active even in production.
        | If false, it's only enabled when APP_DEBUG=true.
        */
        'allow_on_production' => env('GRAPHQL_ALLOW_PRODUCTION', false),

        /*
        | Optional: Closure or class@method to check if the user is allowed
        | to access the GraphQL API in production.
        | Example: ['App\Policies\GraphQLPolicy', 'view']
        */
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to the /graphql endpoint itself.
    | Individual operations inherit middleware from their originating REST routes.
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Eager Loading (N+1 Solution)
    |--------------------------------------------------------------------------
    | Automatically detect and apply with() calls based on the GraphQL 
    | selection set. Eliminates N+1 query performance issues.
    */
    'eager_loading'    => true,
    'eager_load_depth' => 5, // Maximum nesting level to prevent infinite recursion

    /*
    |--------------------------------------------------------------------------
    | Schema Caching
    |--------------------------------------------------------------------------
    | Cache the generated GraphQL schema for performance in production.
    | Disable during development so schema changes are reflected instantly.
    */
    'cache_schema' => env('GRAPHQL_CACHE', true), // Recommendation: true in production
    'cache_ttl'    => 3600, // seconds
    'cache_key'    => 'autographql.schema',

    /*
    |--------------------------------------------------------------------------
    | Model Namespace
    |--------------------------------------------------------------------------
    | The namespace where your Eloquent models live.
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    */
    'default_limit' => 20,
    'max_limit'     => 100,

];
