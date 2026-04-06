<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use MrNamra\AutoGraphQL\Http\GraphQLController;

class GraphQLAutoPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind core classes as singletons
        $this->app->singleton(RouteScanner::class);
        $this->app->singleton(RelationshipDetector::class);
        $this->app->singleton(TypeGenerator::class);
        $this->app->singleton(EagerLoadingAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
        $this->app->singleton(ResolverProxy::class);
        $this->app->singleton(QueryGenerator::class);
        $this->app->singleton(MutationGenerator::class);

        // Merge default config
        $this->mergeConfigFrom(__DIR__.'/../config/autographql.php', 'autographql');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Publish assets
        $this->publishes([
            __DIR__.'/../config/autographql.php' => config_path('autographql.php'),
        ], 'autographql-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'autographql');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/autographql'),
        ], 'autographql-views');

        // 2. Register Routes
        $this->registerRoutes();
    }

    /**
     * Register package routes with protection logic.
     */
    protected function registerRoutes(): void
    {
        $endpoint = config('autographql.endpoint', '/graphql');
        $middleware = config('autographql.middleware', ['api']);

        // GraphQL API Endpoint
        Route::middleware($middleware)
             ->post($endpoint, [GraphQLController::class, 'handle'])
             ->name('autographql.endpoint');

        // GraphiQL Playground
        if ($this->shouldEnablePlayground()) {
            Route::middleware($middleware)
                 ->get('/graphiql', [GraphQLController::class, 'playground'])
                 ->name('autographql.playground');
        }
    }

    /**
     * Check if the playground should be enabled based on config and environment.
     */
    protected function shouldEnablePlayground(): bool
    {
        $config = config('autographql.playground', []);
        $enabled = $config['enabled'] ?? env('APP_DEBUG', false);
        
        if (app()->isProduction()) {
            return config('autographql.production.allow_on_production', false) && $enabled;
        }

        return $enabled;
    }
}
