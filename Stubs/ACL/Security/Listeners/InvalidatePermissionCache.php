<?php
declare(strict_types=1);

namespace App\Domain\Security\Listeners;

use App\Domain\Security\Events\PermissionsChanged;
use App\Domain\Security\Services\CachedAuthorizationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that invalidates per-user caches when PermissionsChanged event is received.
 * This listener is queueable to avoid slowing down ACL mutations.
 */
final class InvalidatePermissionCache implements ShouldQueue
{
    public function handle(PermissionsChanged $event): void
    {
        $service = app(CachedAuthorizationService::class);
        foreach ($event->affectedUserIds as $id) {
            $service->invalidateUserCache((int)$id);
        }
    }
}
