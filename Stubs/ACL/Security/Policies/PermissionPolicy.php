<?php
declare(strict_types=1);

namespace App\Domain\Security\Policies;

use App\Models\User;
use App\Domain\Security\Contracts\AuthorizationServiceInterface;

/**
 * Internal policy example for security models. Typically, only privileged admin users will have these abilities.
 */
final class PermissionPolicy
{
    public function __construct(private AuthorizationServiceInterface $auth) {}

    public function manage(User $user): bool
    {
        // We can use the auth service itself to enforce admin-level checks
        // (e.g., 'security.manage' is a permission string configured in Security domain)
        return $this->auth->check($user, 'security.manage');
    }
}
