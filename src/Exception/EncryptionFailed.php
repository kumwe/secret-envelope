<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use RuntimeException;

/**
 * Signals that libsodium or the random source refused to seal a value.
 *
 * This is the rare, environmental failure: the nonce could not be drawn from the platform's random source or the
 * AEAD primitive rejected its inputs. It stops the write. The underlying engine exception is deliberately not
 * chained, because an engine frame carries the key and the plaintext as call arguments, and an exception that
 * reaches an error tracker must not carry either.
 *
 * @since  0.1.0
 */
final class EncryptionFailed extends RuntimeException implements SecretException
{
}
