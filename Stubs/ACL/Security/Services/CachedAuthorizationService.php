<?php
declare(strict_types=1);

namespace App\Domain\Security\Services;

use App\Domain\Security\Contracts\AuthorizationServiceInterface;
use App\Models\User;
use App\Domain\Security\ValueObjects\AuthorizationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Domain\Security\Exceptions\AuthorizationServiceUnavailableException;

/**
 * CachedAuthorizationService
 *
 * Production-ready wrapper that adds:
 *  - request-level memoization (avoid repeated cache hits during a single HTTP request)
 *  - Redis (or configured cache) backing for per-user permission sets with versioning
 *  - fallback to EloquentAuthorizationService on cache miss or when cache is expired
 *
 * Behavior: on internal errors, throws AuthorizationServiceUnavailableException (caller should treat as deny by default)
 */
final class CachedAuthorizationService implements AuthorizationServiceInterface
{
    private EloquentAuthorizationService $evaluator;
    /** @var array<int,array<string,bool>> in-request memoization: [userId => [ability => bool]] */
    private array $requestMemo = [];
    private string $cachePrefix = 'security:user:permissions:';

    public function __construct(EloquentAuthorizationService $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    public function check(User $user, string $ability, ?AuthorizationContext $context = null): bool
    {
        $userId = $user->getKey();
        // request memoization
        $this->requestMemo[$userId] ??= [];

        if (array_key_exists($ability, $this->requestMemo[$userId])) {
            return $this->requestMemo[$userId][$ability];
        }

        try {
            $map = $this->getPermissionMapFor($user, [$ability], $context);
            $allowed = $map[$ability] ?? false;
            $this->requestMemo[$userId][$ability] = $allowed;
            return $allowed;
        } catch (\Throwable $e) {
            // Translate to typed exception for caller to decide (fail-closed recommended)
            throw new AuthorizationServiceUnavailableException('Authorization check failed', 0, $e);
        }
    }

    public function getPermissionsFor(User $user): array
    {
        $userId = $user->getKey();
        $key = $this->buildCacheKey($userId);
        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            // Fallback to evaluator
            $perms = $this->evaluator->getPermissionsFor($user);
            Cache::put($key, $perms, now()->addMinutes(10));
            return $perms;
        } catch (\Throwable $e) {
            throw new AuthorizationServiceUnavailableException('Failed to read permissions cache', 0, $e);
        }
    }

    public function getPermissionMapFor(User $user, array $abilities, ?AuthorizationContext $context = null): array
    {
        $userId = $user->getKey();
        $key = $this->buildCacheKey($userId);
        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                // evaluate requested abilities against cached permission set
                $map = [];
                foreach ($abilities as $a) {
                    $map[$a] = in_array($a, $cached, true);
                }
                return $map;
            }
            // cache miss: compute via evaluator and store canonical list
            $perms = $this->evaluator->getPermissionsFor($user);
            Cache::put($key, $perms, now()->addMinutes(10));
            $map = [];
            foreach ($abilities as $a) {
                $map[$a] = in_array($a, $perms, true);
            }
            return $map;
        } catch (\Throwable $e) {
            throw new AuthorizationServiceUnavailableException('Failed to compute permission map', 0, $e);
        }
    }

    public function invalidateUserCache(int $userId): void
    {
        $key = $this->buildCacheKey($userId);
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            // Log and swallow to not fail ACL mutation flows; listeners should be retryable
            report($e);
        }
        // bump request memo too
        unset($this->requestMemo[$userId]);
    }

    private function buildCacheKey(int $userId): string
    {
        // Example versioning: you can keep a global ACL version if needed
        $version = Cache::get('security:acl:version', 1);
        return $this->cachePrefix . $userId . ':v' . (string) $version;
    }
}
