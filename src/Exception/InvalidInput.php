<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use InvalidArgumentException;

/**
 * Refuses a caller-supplied value that would push a cipher or a binding outside its safe bounds.
 *
 * Raised when a plaintext or its associated data exceeds the documented size limit, or when an associated-data
 * coordinate would make two different bindings produce the same bytes. Both are checked before any key material
 * is read, so an oversized or ambiguous input never reaches libsodium.
 *
 * @since  0.1.0
 */
final class InvalidInput extends InvalidArgumentException implements SecretException
{
}
