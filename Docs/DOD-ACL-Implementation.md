# **<p align="center">Access Controlling System (ACL) with DOD Structure - Implementation</p>**
**هدف سند این است که توسعه‌دهنده بتواند یک Domain معمولی در پروژهٔ با ساختار DOD را تبدیل به یک security Domain کند .**

---

> **فرض اولیه:** شما یک پروژهٔ لاراول با ساختار DOD دارید و می‌خواهی یکی از domainها را (مثلاً `Security`) به صورت رسمی به مرجع ACL تبدیل کنید.  

---

# مسیر گام‌به‌گام تبدیل Domain به Security Domain

> ترتیب را دقیقاً دنبال کن — این ترتیب تضمین می‌کند وابستگی‌ها و تست‌پذیری حفظ شود.

## گام 1 — ساخت Domain skeleton
اگر پروژه‌ات ابزار `make:domain` دارد از آن استفاده کن، در غیر این صورت دستی بساز.

- با artisan (در صورت وجود):
```bash
php artisan make:domain Security
```

**هدف:** پوشهٔ `app/Domain/Security` را بساز و زیرپوشه‌های لازم را ایجاد کن. از همین ابتدا `Contracts` و `Services` را بساز چون بقیه چیزها به آن‌ها وابسته خواهند شد.

---

## گام 2 — تعریف Contract (قرارداد) — نقطهٔ شروع همه چیز
**فایل زیر را بسازید چون سایر domainها فقط به این قرارداد وابسته می‌شوند؛ نه به پیاده‌سازی. این موجب قابلیت جایگزینی، تست‌پذیری و کاهش coupling می‌شود.** 

```bash
app/Domain/Security/Contracts/AuthorizationServiceInterface.php
```

سایر domainها فقط به این قرارداد وابسته می‌شوند؛ نه به پیاده‌سازی. این موجب قابلیت جایگزینی، تست‌پذیری و کاهش coupling می‌شود.

**نمونهٔ کد :**
> **<p align="center">File: [`...\stubs\ACL\Security\Contracts\AuthorizationServiceInterface.php`](/../../tree/main/Stubs/ACL/Security/Contracts/AuthorizationServiceInterface.php)</p>**
```php
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
```

**نکات عملی:**
- این متدها باید typed باشند و در docblockها رفتار استثنا را توضیح دهی (مثلاً در صورت خطای زیرساخت `AuthorizationServiceUnavailableException` پرتاب می‌شود).  
- قرارداد صرفاً برای خواندن/ارزیابی است. هرگونه mutation (assign/revoke) از طریق Actions انجام شود.

---

## گام 3 — Value Object برای Context
**فایل زیر را بسازید چون به‌جای ارسال آرایهٔ آزاد برای context، یک VO تایپ‌شده باعث self-documenting و خطایابی بهتر می‌شود.** 

```bash
app/Domain/Security/ValueObjects/AuthorizationContext.php
```


**نمونهٔ کد:**
> **<p align="center">File: [`...\stubs\ACL\Security\ValueObjects\AuthorizationContext.php`](/../../tree/main/Stubs/ACL/Security/ValueObjects/AuthorizationContext.php)</p>**
```php
<?php
declare(strict_types=1);

namespace App\Domain\Security\ValueObjects;

/**
 * Small value object for typed, self-documenting context passed into authorization checks.
 * Use this instead of ad-hoc associative arrays when context gets complex.
 */
final class AuthorizationContext
{
    public function __construct(
        public readonly ?int $resourceId = null,
        public readonly ?int $tenantId = null,
        public readonly array $meta = [],
    ) {}
}
```

**نکات عملی:** اگر context پیچیده شد، اضافه‌کردن props جدید بهتر از استفاده از meta‌ آرایه‌ای است؛ اما meta برای موارد ad-hoc نگه داشته شود.

---

## گام 4 — Exceptions مخصوص Authorization
**فایل زیر را بسازید برای این که تعریف یک exception تایپ‌شده برای شناسایی خطاهای زیرساخت (DB/Cache down) مهم است چون رفتار fail‑closed یا fallback را کنترل خواهیم کرد.**



```bash
app/Domain/Security/Exceptions/AuthorizationServiceUnavailableException.php
```
**نمونهٔ کد:**
> **<p align="center">File: [`...\stubs\ACL\Security\Exceptions\AuthorizationServiceUnavailableException.php`](/../../tree/main/Stubs/ACL/Security/Exceptions/AuthorizationServiceUnavailableException.php)</p>**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Security\Exceptions;

use RuntimeException;

final class AuthorizationServiceUnavailableException extends RuntimeException {}
```

---

## گام 5 — پیاده‌سازی canonical evaluator
**فایل زیر را بسازید چون این کلاس محل نگاشت قوانین تجاری canonial (بدون cache) است — ترکیب roleها، per-user overrideها، explicit denyها، محدودیت‌ها (limits) و context‑aware checks.**

```bash
app/Domain/Security/Services/EloquentAuthorizationService.php
```



**نکات اجرایی برای نوشتن کد:**
1. همیشه با بررسی سریع blocked user شروع کن (fast-fail).  
2. برای یافتن permission مربوط به ability از جدولِ permission یا منبع مجاز استفاده کن. اگر permission یافت نشد → deny.  
3. ابتدا per-user override را بررسی کن (اگر explicit allow/deny هست آن‌را اعمال کن).  
4. سپس نقش‌ها را aggregate کن (اگر هر نقشی اجازه داد → allow).  
5. پس از آن constraints مربوط به context را اعمال کن (owner, tenant, resource status).  
6. اگر permission دارای limit است (مثلاً monthly_count)، `getUsageCount()` صدا زده شود و چک شود در محدوده مجاز هست یا نه.  

**نمونهٔ کد:**
> **<p align="center">File: [`...\stubs\ACL\Security\Services\EloquentAuthorizationService.php`](/../../tree/main/Stubs/ACL/Security/Services/EloquentAuthorizationService.php)</p>**
```php
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
```

**نکات مهم عملکردی:**

- Extract helper private methods for `isUserBlocked()`, `resolvePermission()`, `isAllowedByRoles()`, `passesContextConstraints()` and `getUsageCount()` to simplify unit testing.
- `getUsageCount()` should be implemented in a high-performance way — Redis counters or aggregated audit table. The choice depends on your volume and SLA.

---

## گام 6 — پیاده‌سازی cached wrapper
**فایل زیر را جهت افزایش کارایی سیستم و کاهش فشار از روی پایگاه داده به صورت ایجاد بکنید که ویژگی های زیر موجود باشند.**

```bash
app/Domain/Security/Services/CachedAuthorizationService.php
```

- Request-level memoization (no repeated reads within an HTTP request).
- Read/write cache (Redis) with key versioning or tags.
- Fallback to Eloquent evaluator on cache miss or if needed.
- `InvalidateUserCache()` method called by listeners.

**نمونهٔ کد:**
> **<p align="center">File: [`...\stubs\ACL\Security\Services\CachedAuthorizationService.php`](/../../tree/main/Stubs/ACL/Security/Services/CachedAuthorizationService.php)</p>**
```php
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

```

**نکات :**
- مقادیر TTL و cache prefix در `config/security.php` قرار گیرد.  
- استفاده از versioning یا tags برای invalidation بهتر از پاک کردن کلیدهای جداگانه در bulk است.  
- رفتار بر اثر خطا: اگر `fail_closed` تنظیم شده باشد، `CachedAuthorizationService` استثنا را به caller پاس دهد یا false برگرداند (سیاست تیم را مستند کن).

---

## گام 7 — تعریف Events برای انتشار تغییرات ACL
**فایل زیر را بسازید تا وقتی mutation روی ACL انجام می‌شود (assign/revoke/override) این Event منتشر شود تا Listener آن را دریافت کند و cache را invalidate کند.** 

```bash
app/Domain/Security/Events/PermissionsChanged.php
```

**نمونهٔ کد:**
> **<p align="center">File: [`...\stubs\ACL\Security\Events\PermissionsChanged.php`](/../../tree/main/Stubs/ACL/Security/Events/PermissionsChanged.php)</p>**
```php
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

```

**نکته:** payload فقط `affectedUserIds` یا یک token version است — چیزی بزرگ ارسال نکن.

---

## گام 8 — نوشتن Listener برای invalidation (Queueable)
**فایل زیر را جهت شنیدن `PermissionsChanged` و پاک‌سازی cacheهای کاربران مربوطه بسازید. Listener باید Queueable باشد و robust برای retry.**

```bash
app/Domain/Security/Listeners/InvalidatePermissionCache.php
```

**نمونهٔ ساده:**
> **<p align="center">File: [`...\stubs\ACL\Security\Listeners\InvalidatePermissionCache.php`](/../../tree/main/Stubs/ACL/Security/Listeners/InvalidatePermissionCache.php)</p>**
```php
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
```

**نکات عملی:**
- باید chunking انجام شود؛ برای جلوگیری از timeout
- توجه داشته باشید که listener نباید throw کند؛ خطاها را گزارش و retry کن.  
- ثبت متریک‌ در handle (how many invalidated) کمک به monitoring می‌کند.

---

## گام 9 — Action های دامنهٔ Security
**فایل‌ها:** `app/Domain/Security/Actions/AssignRoleAction.php`, `RevokeRoleAction.php`, `UpdatePermissionAction.php` و غیره.

**هدف:** همهٔ تغییرات ACL از طریق Actions انجام شوند تا مکانیزمِ auditing، transaction و event dispatch قابل کنترل بماند.

**نمونهٔ AssignRoleAction (skeleton):**
> **<p align="center">File: [`...\stubs\ACL\Security\Actions\AssignRoleAction.php`](/../../tree/main/Stubs/ACL/Security/Actions/AssignRoleAction.php)</p>**
```php
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

```

**نکات عملی:**
- حتماً در Action: validation, authorization (only admin), audit logging و transaction را انجام بده.  
- هر Action باید deterministic، retryable (یا idempotent) و تست‌پذیر باشد.

---

## گام 10 — Service Provider و bindingها
**در این فایل provider binding قرارداد به پیاده‌سازی را انجام می‌دهد و event listenerها را رجیستر می‌کند.**

```bash
app/Domain/Security/Providers/SecurityServiceProvider.php
```

**نمونهٔ :**
> **<p align="center">File: [`...\stubs\ACL\Security\Providers\SecurityServiceProvider.php`](/../../tree/main/Stubs/ACL/Security/Providers/SecurityServiceProvider.php)</p>**
```php
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
```

**نکات عملی:**
- ثبت provider باید صورت گیرد.
- binding به‌صورت singleton یا bind با توجه به نیاز (singleton for evaluator often fine).  
- provider نقطهٔ مناسب برای register کردن policy mappings (Gate).

---

## گام 11 — استفاده در سایر Domainها (پالیسی‌ها و FormRequest)
**گام به گام:**
1. در هر policy یا action در دامنهٔ مصرف‌کننده (حتی خود این دامنه)، constructor DI را اضافه کن :
```php
public function __construct(\App\Domain\Security\Contracts\AuthorizationServiceInterface $auth) { $this->auth = $auth; }
```
2. در متدهای policy، یک `AuthorizationContext` درست کن و delegate کن:
```php
public function update(User $user, Post $post): bool
{
    $context = new AuthorizationContext(resourceId: $post->id, tenantId: $post->tenant_id ?? null, meta: ['owner_id'=>$post->user_id]);
    return $this->auth->check($user, 'post.update', $context);
}
```
3. در FormRequest::authorize یا در کنترلر، از همان policy یا مستقیم از interface استفاده کن.

**نکته عملی:** همیشه server-side check را داشته باش؛ UI-only checks فقط UX هستند.

**PostPolicy.php**

> **<p align="center">File: [`...\stubs\ACL\Blog\Policies\PostPolicy.php`](/../../tree/main/Stubs/ACL/Blog/Policies/PostPolicy.php)</p>**
```php
<?php
declare(strict_types=1);

namespace App\Domain\Blog\Policies;

use App\Models\User;
use App\Domain\Blog\Models\Post;
use App\Domain\Security\Contracts\AuthorizationServiceInterface;
use App\Domain\Security\ValueObjects\AuthorizationContext;

/**
 * Consumer domain policy example showing proper DI and context passing.
 * - The policy constructs a small AuthorizationContext (owner, resource id)
 * - Delegates decision to Security domain via AuthorizationServiceInterface
 */
final class PostPolicy
{
    public function __construct(private AuthorizationServiceInterface $auth) {}

    public function update(User $user, Post $post): bool
    {
        $context = new AuthorizationContext(resourceId: $post->getKey(), tenantId: $post->tenant_id ?? null);
        return $this->auth->check($user, 'post.update', $context);
    }
}
```

**PermissionPolicy.php**
> **<p align="center">File: [`...\stubs\ACL\Security\Policies\PermissionPolicy.php`](/../../tree/main/Stubs/ACL/Security/Policies/PermissionPolicy.php)</p>**
```php
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
```

---

## گام 12 — config, env و runtime toggles
**فایل پیشنهادی:** `config/security.php`
```php
return [
  'cache_ttl_minutes' => env('SECURITY_CACHE_TTL', 10),
  'cache_prefix' => env('SECURITY_CACHE_PREFIX', 'security:user:permissions:'),
  'fail_closed' => env('SECURITY_FAIL_CLOSED', true),
  'use_versioning' => env('SECURITY_USE_VERSIONING', true),
];
```

**ENV keys:** `SECURITY_CACHE_TTL`, `SECURITY_CACHE_PREFIX`, `SECURITY_FAIL_CLOSED`.

**<p align="center">[Cache in laravel](https://laravel.com/docs/12.x/cache)</p>**

---

## خلاصه :
- `Contracts/AuthorizationServiceInterface` ← The hub of communication between Security and the rest of the system.
- `ValueObjects/AuthorizationContext`  ← Type-safe context formatting. 
- `Services/EloquentAuthorizationService` ← canonical business logic (DB).  
- `Services/CachedAuthorizationService` ← performance wrapper (Cache + memoization + error handling).  
- `Actions/*` ← mutation endpoints (transaction + audit + event).  
- `Events/PermissionsChanged` ← Publication of ACL changes. 
- `Listeners/InvalidatePermissionCache` ←  Cache clearing (queueable). 
- `Providers/SecurityServiceProvider` ← binding + event wiring + optional Gate registrations.  
- `Policies (consumer)` ← constructor DI -> build context -> delegate to service.

---

> **<p align="center">Read more about [`Service Container`](https://laravel.com/docs/12.x/container), [`Service Providers`](https://laravel.com/docs/12.x/providers) and [`Cache`](https://laravel.com/docs/12.x/cache) in laravel</p>**
