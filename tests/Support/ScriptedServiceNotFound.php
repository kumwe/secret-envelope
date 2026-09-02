<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Support;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Test support: the not-found refusal of the scripted container.
 *
 * @since  0.1.0
 */
final class ScriptedServiceNotFound extends RuntimeException implements NotFoundExceptionInterface
{
}
