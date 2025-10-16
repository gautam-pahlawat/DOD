<?php
declare(strict_types=1);

namespace App\Domain\Security\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Security\Contracts\AuthorizationServiceInterface;
use App\Domain\Security\Services\CachedAuthorizationService;
use App\Domain\Security\Services\EloquentAuthorizationService;
use App\Domain\Security\Listeners\InvalidatePermissionCache;
use App\Domain\Security\Events\PermissionsChanged;

/**
 * SecurityServiceProvider
 *
 * Registers bindings, event listeners and policy mappings specific to Security domain.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind canonical evaluator first (single responsibility)
        $this->app->singleton(EloquentAuthorizationService::class, function ($app) {
            return new EloquentAuthorizationService();
        });

        // bind cached wrapper as the AuthorizationServiceInterface implementation
        $this->app->bind(AuthorizationServiceInterface::class, function ($app) {
            return new CachedAuthorizationService($app->make(EloquentAuthorizationService::class));
        });
    }

    public function boot(): void
    {
        // register event listeners
        \Illuminate\Support\Facades\Event::listen(
            PermissionsChanged::class,
            [InvalidatePermissionCache::class, 'handle']
        );

        // Optionally register policies here with Gate::policy() or Gate::define()
        // Example (if Permission model exists): // Gate::policy(Permission::class, PermissionPolicy::class);
    }
}
