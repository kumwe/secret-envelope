<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use Kumwe\Secret\Value\KeyIdentifier;
use RuntimeException;

/**
 * Signals that the key an envelope names is not held by this process.
 *
 * This is deliberately a different answer from a failed authentication. An envelope whose key the provider does
 * not hold has not been tampered with: it was sealed under a key that has been retired without being kept,
 * revoked by an external provider, derived for another purpose, or that belongs to another installation
 * entirely. Telling the two apart is what lets an operator distinguish "restore the key" from "this ciphertext
 * is not what it claims". A purpose mismatch surfaces here too, because each purpose holds its own ring and its
 * own identifiers, so an envelope presented to the wrong purpose names a key that ring does not hold.
 *
 * The message names the requested identifier only, never key material, plaintext, or the identifiers of the
 * keys that are held: an unavailable-key error reaching a log must not become a map of the ring.
 *
 * @since  0.1.0
 */
final class KeyUnavailable extends RuntimeException implements SecretException
{
    /**
     * Identifier the caller asked for, or `unnamed` when it did not even match the identifier grammar.
     *
     * @var    string
     * @since  0.1.0
     */
    private readonly string $keyId;

    /**
     * Report that one named key is not available for use.
     *
     * @param  string  $keyId  Identifier the envelope or caller named. It is a key name, never key bytes, and is
     *         included so an operator can tell which key to restore; a malformed identifier is reported without
     *         being echoed.
     *
     * @since  0.1.0
     */
    public function __construct(string $keyId)
    {
        $this->keyId = KeyIdentifier::isValid($keyId) ? $keyId : 'unnamed';
        parent::__construct(sprintf('The secret encryption key "%s" is unavailable.', $this->keyId));
    }

    /**
     * Name the key that was asked for, so a host can route the refusal without parsing the message.
     *
     * @return  string  The requested identifier, or `unnamed` when it was malformed.
     *
     * @since   0.1.0
     */
    public function keyId(): string
    {
        return $this->keyId;
    }
}
