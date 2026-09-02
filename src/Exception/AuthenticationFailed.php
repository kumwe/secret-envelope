<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use RuntimeException;

/**
 * Signals that an envelope did not authenticate under the key it names and the binding it was given.
 *
 * Any single altered byte of the ciphertext or nonce, any difference in the associated data, a truncated
 * ciphertext, or a key whose bytes differ from the ones that sealed the envelope ends here. The package never
 * says which of those it was: distinguishing them would hand an attacker an oracle, so every authentication
 * failure is one indistinguishable refusal. A key the process does not hold at all is a different answer,
 * `KeyUnavailable`, because that is recoverable and this is not.
 *
 * @since  0.1.0
 */
final class AuthenticationFailed extends RuntimeException implements SecretException
{
}
