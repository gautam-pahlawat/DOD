<?php
declare(strict_types=1);

namespace App\Domain\Security\Actions;

use App\Models\User;
use App\Domain\Security\Events\PermissionsChanged;

/**
 * AssignRoleAction
 *
 * Perform role assignment in a transactional, auditable way.
 * This is a minimal stub: implement DB logic, validation and audit as needed.
 */
final class AssignRoleAction
{
    public function execute(User $user, int $roleId): void
    {
        // Begin transaction, attach role, commit, then emit event:
        // DB::transaction(function() use ($user, $roleId) { ... });
        // After successful mutation:
        PermissionsChanged::dispatch([$user->getKey()]);
    }
}
