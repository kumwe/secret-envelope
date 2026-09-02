<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use LogicException;

/**
 * Refuses an operation that would copy key material into a form the package cannot redact.
 *
 * `serialize()` on a `KeyMaterial` would write the raw key into a string that then travels through caches,
 * queues, sessions and logs with no redaction hook of its own. The value refuses rather than complying, and
 * this is the refusal: a programming error, reported as a `LogicException`, that must be fixed at the call site.
 *
 * @since  0.1.0
 */
final class SecretDisclosureRefused extends LogicException implements SecretException
{
}
