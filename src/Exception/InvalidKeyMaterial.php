<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use InvalidArgumentException;

/**
 * Refuses key material, a key identifier, a key ring or a key purpose that cannot be used safely.
 *
 * Raised at construction, which is the only moment configuration is admitted: a key of the wrong size, an
 * identifier outside the accepted grammar, a ring that would hold one identifier twice or more retired keys
 * than it bounds, or a purpose with an empty or malformed derivation label. This is a deployment fault, never
 * a data fault, and it stops a process from starting rather than letting it seal under an unusable key.
 * The message names the rule that was broken and never the bytes that broke it.
 *
 * @since  0.1.0
 */
final class InvalidKeyMaterial extends InvalidArgumentException implements SecretException
{
}
