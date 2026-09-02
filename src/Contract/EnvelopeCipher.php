<?php

declare(strict_types=1);

namespace Kumwe\Secret\Contract;

use Kumwe\Secret\Value\EncryptedEnvelope;
use SensitiveParameter;

/**
 * Port through which values are sealed into envelopes and opened again.
 *
 * A host reaches for this wherever a value must never reach storage in the clear, so no plaintext ever reaches a
 * column and the persistence layer never sees a key. Implementations owe authenticated encryption: the
 * associated data is authenticated but not stored, which is what stops an envelope from being replayed into a
 * different place. Keeping this a port is what lets the key source, libsodium in one process or a managed KMS in
 * another, change without touching the code that stores envelopes.
 *
 * @since  0.1.0
 */
interface EnvelopeCipher
{
    /**
     * Seal a value into an envelope bound to the caller's associated data.
     *
     * An implementation must draw a fresh nonce per call, so sealing the same plaintext twice yields different
     * envelopes and stored ciphertext never reveals that two places hold the same value.
     *
     * @param   string  $plaintext       Value to protect, at most the implementation's documented bound; only the
     *          returned envelope is persisted.
     * @param   string  $associatedData  Binding such as `AssociatedData::for()` composes, authenticated but not
     *          stored, at most the implementation's documented bound.
     *
     * @return  EncryptedEnvelope  Ciphertext, nonce and key identifier, ready to be written to storage.
     *
     * @throws  \Kumwe\Secret\Exception\InvalidInput  When the plaintext or the associated data exceeds its bound.
     * @throws  \Kumwe\Secret\Exception\KeyUnavailable  When the implementation cannot produce the key new
     *          envelopes are sealed under, which must stop the write rather than degrade it.
     * @throws  \Kumwe\Secret\Exception\EncryptionFailed  When the random source or the AEAD primitive refuses.
     *
     * @since   0.1.0
     */
    public function encrypt(#[SensitiveParameter] string $plaintext, string $associatedData): EncryptedEnvelope;

    /**
     * Open an envelope and return the value it protects.
     *
     * Decryption fails closed. An implementation must refuse rather than return a value when the envelope names
     * a key it does not hold, when the ciphertext or nonce has been altered, or when the associated data differs
     * by so much as one byte from the binding used to seal it, which is how an envelope copied between places
     * is caught.
     *
     * @param   EncryptedEnvelope  $envelope        Envelope as read back from storage.
     * @param   string             $associatedData  The same binding that was supplied to `encrypt()`.
     *
     * @return  string  The original plaintext, byte for byte.
     *
     * @throws  \Kumwe\Secret\Exception\InvalidEnvelope  When the envelope names an unsupported construction.
     * @throws  \Kumwe\Secret\Exception\InvalidInput  When the associated data exceeds its bound.
     * @throws  \Kumwe\Secret\Exception\KeyUnavailable  When the envelope names a key the implementation does not
     *          hold, which is a distinct condition from a failed authentication.
     * @throws  \Kumwe\Secret\Exception\AuthenticationFailed  When the ciphertext, nonce, key bytes or associated
     *          data do not authenticate together.
     *
     * @since   0.1.0
     */
    public function decrypt(EncryptedEnvelope $envelope, string $associatedData): string;
}
