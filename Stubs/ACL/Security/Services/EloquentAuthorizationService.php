<?php
declare(strict_types=1);

namespace App\Domain\Security\Services;

use App\Domain\Security\Contracts\AuthorizationServiceInterface;
use App\Domain\Security\ValueObjects\AuthorizationContext;
use App\Domain\Security\Exceptions\AuthorizationServiceUnavailableException;
use App\Domain\Security\Models\Permission;
use App\Domain\Security\Models\Role;
use App\Domain\Security\Models\UserPermission;
use App\Domain\Security\Models\PermissionLimit;
use App\Domain\Security\Models\BlockedUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * EloquentAuthorizationService
 *
 * Production-ready canonical evaluator sketch.
 *
 * Responsibilities:
 * - Resolve user effective permissions by combining roles, direct per-user overrides and global denies.
 * - Support context-aware checks (ownership, tenant, resource status).
 * - Enforce simple permission limits via PermissionLimit (e.g., monthly_count), stubbed for integration with counters.
 * - Throw AuthorizationServiceUnavailableException on system-level failures.
 *
 * Important implementation notes:
 * - This class performs direct DB reads. It's intended to be called by a CachedAuthorizationService wrapper
 *   in production to avoid repeated DB hits.
 * - Keep logic deterministic and testable. Complex sub-parts should be extracted to private methods to allow unit testing.
 * - Replace placeholder methods (e.g. getUsageCount) with integrations to your metrics/counters (Redis, DB, etc.).
 */
final class EloquentAuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Check whether the user has given ability under context.
     *
     * Flow (high-level):
     *  - quick-block check
     *  - resolve permission id(s) for the ability name
     *  - collect role-derived permissions and per-user overrides
     *  - apply explicit denies (user-level deny should override role allows)
     *  - evaluate context-aware constraints (owner, tenant, status)
     *  - evaluate permission limits (calls out to getUsageCount)
     *
     * @throws AuthorizationServiceUnavailableException
     */
    public function check(User $user, string $ability, ?AuthorizationContext $context = null): bool
    {
        try {
            // 1) Blocked user quick check (fast-fail)
            if ($this->isUserBlocked($user)) {
                return false;
            }

            // 2) Resolve permission record(s) for ability name
            $permission = Permission::where('action', $ability)->first();
            if (! $permission) {
                // Unknown permission => default deny (fail-closed)
                return false;
            }

            $permissionId = $permission->getKey();

            // 3) Get per-user override (explicit allow/deny)
            $override = UserPermission::where('user_id', $user->getKey())
                ->where('permission_id', $permissionId)
                ->first();

            if ($override) {
                // explicit per-user allow/deny takes precedence
                return (bool) $override->allowed;
            }

            // 4) Aggregate role permissions (allow if any role grants it)
            // assumes User model has roles() relationship; eager loading recommended upstream
            $roleAllowed = $this->isAllowedByRoles($user->getKey(), $permissionId);

            if (! $roleAllowed) {
                // not allowed by roles and no override => deny
                return false;
            }

            // 5) Context-aware checks (owner, tenant, resource status)
            if ($context !== null) {
                if (! $this->passesContextConstraints($user, $permission, $context)) {
                    return false;
                }
            }

            // 6) Permission limits (e.g., monthly uploads)
            $limit = PermissionLimit::where('permission_id', $permissionId)->first();
            if ($limit !== null) {
                if (! $this->withinLimit($user, $permission, $limit, $context)) {
                    return false;
                }
            }

            // Passed all checks -> allow
            return true;
        } catch (\Throwable $e) {
            // Convert to typed exception to allow callers to decide fail-closed behavior
            throw new AuthorizationServiceUnavailableException('Eloquent authorization failed', 0, $e);
        }
    }

    /**
     * Return canonical list of permission action strings for the user.
     * This is intentionally DB-heavy and used by the cached wrapper.
     *
     * @param User $user
     * @return array<string>
     */
    public function getPermissionsFor(User $user): array
    {
        // Combine role permissions and user explicit allows, minus explicit denies.
        $userId = $user->getKey();

        // role permissions
        $rolePerms = DB::table('role_permission')
            ->join('roles', 'role_permission.role_id', '=', 'roles.id')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->join('user_role', 'user_role.role_id', '=', 'roles.id')
            ->where('user_role.user_id', $userId)
            ->pluck('permissions.action')
            ->unique()
            ->values()
            ->toArray();

        // user explicit allows
        $userAllows = DB::table('user_permissions')
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->where('user_permissions.user_id', $userId)
            ->where('user_permissions.allowed', true)
            ->pluck('permissions.action')
            ->toArray();

        // user explicit denies
        $userDenies = DB::table('user_permissions')
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->where('user_permissions.user_id', $userId)
            ->where('user_permissions.allowed', false)
            ->pluck('permissions.action')
            ->toArray();

        $perms = array_unique(array_merge($rolePerms, $userAllows));
        // remove denies
        $perms = array_values(array_diff($perms, $userDenies));

        return $perms;
    }

    /**
     * Batch check: efficient evaluation for a set of abilities.
     * The cached wrapper should call this to reduce round-trips.
     *
     * @param User $user
     * @param string[] $abilities
     * @param AuthorizationContext|null $context
     * @return array<string,bool>
     */
    public function getPermissionMapFor(User $user, array $abilities, ?AuthorizationContext $context = null): array
    {
        // Naive implementation: compute full list once and map. Implement more optimized strategies if needed.
        $allowedSet = array_flip($this->getPermissionsFor($user));
        $map = [];
        foreach ($abilities as $a) {
            $map[$a] = isset($allowedSet[$a]);
        }

        // For context-sensitive abilities, fallback to single check per ability
        if ($context !== null) {
            foreach ($abilities as $a) {
                if (! $map[$a]) {
                    // re-evaluate in context if necessary
                    $map[$a] = $this->check($user, $a, $context);
                }
            }
        }

        return $map;
    }

    /**
     * Eloquent evaluator does not manage cache; noop
     */
    public function invalidateUserCache(int $userId): void
    {
        // no-op: caching layer handles invalidation
    }

    /* ---------- private helpers ---------- */

    private function isUserBlocked(User $user): bool
    {
        $blocked = BlockedUser::where('user_id', $user->getKey())
            ->where(function ($q) {
                $q->whereNull('blocked_until')
                  ->orWhere('blocked_until', '>', Carbon::now());
            })->exists();

        return $blocked;
    }

    private function isAllowedByRoles(int $userId, int $permissionId): bool
    {
        // optimized join to check if any role of user grants permission
        $exists = DB::table('user_role')
            ->join('role_permission', 'user_role.role_id', '=', 'role_permission.role_id')
            ->where('user_role.user_id', $userId)
            ->where('role_permission.permission_id', $permissionId)
            ->exists();

        return $exists;
    }

    private function passesContextConstraints(User $user, Permission $permission, AuthorizationContext $context): bool
    {
        // Example constraints (implement based on your domain rules):
        // - If permission requires ownership, check resource owner_id matches user id
        // - If permission is tenant-scoped, check tenant_id matches context.tenantId
        // Implement real checks based on how your Permission model encodes constraints.
        if (! empty($context->resourceId)) {
            // If permission requires ownership (this is domain-specific; adapt accordingly)
            if ($permission->requires_owner ?? false) {
                // Attempt to resolve the resource model dynamically is out-of-scope here;
                // instead expect callers to pass owner_id in context.meta when needed.
                $ownerId = $context->meta['owner_id'] ?? null;
                if ($ownerId === null || (int)$ownerId !== (int)$user->getKey()) {
                    return false;
                }
            }
        }

        if (! empty($context->tenantId)) {
            if (isset($permission->tenant_scoped) && $permission->tenant_scoped) {
                if ((int)$context->tenantId !== (int)($user->tenant_id ?? 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function withinLimit(User $user, Permission $permission, PermissionLimit $limit, ?AuthorizationContext $context = null): bool
    {
        // Simple example: monthly_count limit type
        if ($limit->limit_type === 'monthly_count') {
            $count = $this->getUsageCount($user->getKey(), $permission->getKey(), 'month', $context);
            return $count < (int)$limit->limit_value;
        }

        // other limit types should be implemented per business needs
        return true;
    }

    /**
     * getUsageCount
     * - Placeholder: integrate with your event logs / counters / usage DB to compute usage in period
     * - For production you may use Redis counters (with date suffix), or query an audit table.
     *
     * @param int $userId
     * @param int $permissionId
     * @param string $period ('day'|'month'|'all')
     * @param AuthorizationContext|null $context
     * @return int
     */
    private function getUsageCount(int $userId, int $permissionId, string $period, ?AuthorizationContext $context = null): int
    {
        // Example naive audit table usage (you should implement an efficient counter):
        // return DB::table('permission_usage')
        //     ->where('user_id', $userId)
        //     ->where('permission_id', $permissionId)
        //     ->whereBetween('created_at', [$start, $end])
        //     ->count();

        // For now return 0 as placeholder (no usage)
        return 0;
    }
}
