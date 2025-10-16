<?php
declare(strict_types=1);

namespace App\Domain\Security\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when ACL entries affecting users change (roles/permissions/overrides).
 * Listeners should invalidate caches and perform audit logging.
 */
final class PermissionsChanged
{
    use Dispatchable;

    public function __construct(public readonly array $affectedUserIds = []) {}
}
