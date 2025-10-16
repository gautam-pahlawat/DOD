<?php
declare(strict_types=1);

namespace App\Domain\Security\Exceptions;

use RuntimeException;

final class AuthorizationServiceUnavailableException extends RuntimeException
{
}
