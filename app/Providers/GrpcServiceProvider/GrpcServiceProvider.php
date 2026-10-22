<?php

namespace App\Providers\GrpcServiceProvider;

use Illuminate\Support\ServiceProvider;

/**
 * Binds every gRPC service into the container and publishes the ordered
 * service map that `worker.php` reads when registering handlers.
 *
 * Handlers resolve their dependencies (Eloquent, services, GraphQL resolvers)
 * through the same container the HTTP and GraphQL entry points use, so there
 * is exactly one object graph per request.
 */
class GrpcServiceProvider extends ServiceProvider
{
    /**
     * gRPC services exposed by the mobile API, in registration order.
     *
     * @var list<class-string>
     */
    public const SERVICES = [
        \App\Grpc\User\V1\AuthService::class,
        \App\Grpc\User\V1\ProfileService::class,
        \App\Grpc\User\V1\CategoryService::class,
        \App\Grpc\User\V1\CatalogService::class,
        \App\Grpc\User\V1\PackageService::class,
        \App\Grpc\User\V1\CbirService::class,
        \App\Grpc\User\V1\OrderService::class,
    ];

    public function register(): void
    {
        foreach (self::SERVICES as $serviceClass) {
            $this->app->singleton($serviceClass);
        }

        // `worker.php` iterates this to register handlers with RoadRunner.
        $this->app->instance('grpc.services.container', self::SERVICES);
    }

    public function boot(): void
    {
        $this->publishes([
            base_path('.rr.yaml') => base_path('.rr.yaml'),
        ], 'grpc-config');
    }
}