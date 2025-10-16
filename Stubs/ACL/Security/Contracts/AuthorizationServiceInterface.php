<?php
declare(strict_types=1);

namespace App\Domain\Security\Contracts;

use App\Models\User;
use App\Domain\Security\ValueObjects\AuthorizationContext;

/**
 * AuthorizationServiceInterface
 *
 * A minimal, explicit contract used by consuming domains (policies, form requests, actions).
 * - Read-only: no mutation methods here (mutations are Actions/Commands in Security domain)
 * - Context-aware: pass an AuthorizationContext for complex checks
 * - Batch-friendly: getPermissionMapFor returns multiple checks in one call (efficient for UI)
 *
 * Implementations MUST be thread-safe and reentrant. Prefer returning boolean for check(), and
 * throwing typed exceptions for system-level failures.
 */
interface AuthorizationServiceInterface
{
    /**
     * Check whether the given user has the named ability under the given context.
     *
     * @param User $user
     * @param string $ability  A canonical ability string e.g. 'post.update'
     * @param AuthorizationContext|null $context Optional structured context (resource ids, tenant id...)
     * @return bool  True if allowed, false otherwise
     *
     * @throws \App\Domain\Security\Exceptions\AuthorizationServiceUnavailableException On system failures (e.g. cache/DB down)
     */
    public function check(User $user, string $ability, ?AuthorizationContext $context = null): bool;

    /**
     * Return a flat array of permission keys for the given user.
     * Useful for diagnostics and non-performance-critical tooling.
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public function getPermissionsFor(User $user): array;

    /**
     * Efficiently evaluate multiple abilities for a user in a single call.
     * Returns an associative map: ability => bool.
     *
     * @param User $user
     * @param string[] $abilities
     * @param AuthorizationContext|null $context
     * @return array<string, bool>
     */
    public function getPermissionMapFor(User $user, array $abilities, ?AuthorizationContext $context = null): array;

    /**
     * Invalidate cached permissions for a user (or bump version).
     * Called by Security domain listeners when ACL changes.
     *
     * @param int $userId
     * @return void
     */
    public function invalidateUserCache(int $userId): void;
}
