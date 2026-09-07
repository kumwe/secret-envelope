<?php

/**
 * Isolated process fixture: force platform failures without replacing any production source.
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Secret\Cipher;

use Random\RandomException;
use SodiumException;

/**
 * Fail nonce acquisition only in the dedicated subprocess scenario.
 * @param int $length Requested nonce size.
 * @return string Random nonce.
 * @since 0.1.0
 */
function random_bytes(int $length): string
{
    if (getenv('KUMWE_TEST_CIPHER_FAILURE') === 'nonce') {
        throw new RandomException('private-platform-detail');
    }
    if ($length < 1) {
        throw new \ValueError('A positive nonce length is required.');
    }
    return \random_bytes($length);
}

/**
 * Inject the platform sealing failure after successful nonce acquisition.
 * @param string $plaintext Synthetic plaintext.
 * @param string $associatedData Synthetic binding.
 * @param string $nonce Synthetic nonce.
 * @param string $key Synthetic key.
 * @return string Native ciphertext when no seal failure is selected.
 * @since 0.1.0
 */
function sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
    string $plaintext,
    string $associatedData,
    string $nonce,
    string $key,
): string {
    if (getenv('KUMWE_TEST_CIPHER_FAILURE') === 'seal') {
        throw new SodiumException('private-platform-detail:' . $plaintext . $associatedData . $nonce . $key);
    }
    return \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $associatedData, $nonce, $key);
}
