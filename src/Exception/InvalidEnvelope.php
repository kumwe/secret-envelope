<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use InvalidArgumentException;

/**
 * Refuses an envelope whose parts are malformed before any key is touched.
 *
 * Raised by `EncryptedEnvelope` when a ciphertext is empty, shorter than the authentication tag or oversized,
 * a nonce has the wrong length, a key identifier is malformed, a stored row is incomplete or not valid base64,
 * or the algorithm is anything but the one supported construction. A downgraded or corrupted stored row is
 * therefore reported as a damaged envelope, which is a data-integrity finding, rather than surfacing later as
 * an authentication failure that looks like tampering.
 *
 * @since  0.1.0
 */
final class InvalidEnvelope extends InvalidArgumentException implements SecretException
{
}
