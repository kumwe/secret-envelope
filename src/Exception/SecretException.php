<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use Throwable;

/**
 * Marker every refusal raised by this package carries, so a host can catch the whole family at one boundary.
 *
 * Each concrete exception also extends the SPL type its condition has always mapped to: `InvalidArgumentException`
 * for a refused input, envelope or configuration, `RuntimeException` for a failure while sealing or opening, and
 * `LogicException` for a disclosure the package refuses to perform. A caller that already catches those keeps
 * working. No message anywhere in the family quotes plaintext or key material.
 *
 * @since  0.1.0
 */
interface SecretException extends Throwable
{
}
