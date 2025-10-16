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
