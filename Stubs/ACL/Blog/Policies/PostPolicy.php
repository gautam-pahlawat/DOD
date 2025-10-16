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
