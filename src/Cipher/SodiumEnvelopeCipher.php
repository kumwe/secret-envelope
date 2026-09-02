<?php

declare(strict_types=1);

namespace Kumwe\Secret\Cipher;

use Kumwe\Secret\Contract\EnvelopeCipher;
use Kumwe\Secret\Exception\AuthenticationFailed;
use Kumwe\Secret\Exception\EncryptionFailed;
use Kumwe\Secret\Exception\InvalidInput;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyIdentifier;
use Kumwe\Secret\Value\KeyMaterial;
use Random\RandomException;
use SensitiveParameter;
use SodiumException;

/**
 * `EnvelopeCipher` backed by libsodium's XChaCha20-Poly1305 AEAD, holding exactly one key.
 *
 * The instance is bound to a single key and the identifier that names it, which it stamps into every envelope it
 * writes and insists on again before decrypting: an envelope written under a different key is reported as
 * unavailable rather than attempted, so rotation means wiring a `KeyRingEnvelopeCipher` and never means trying
 * keys until one works. Each encryption draws a fresh random nonce from the platform's random source, and the
 * caller's associated data is authenticated but not stored, so a ciphertext copied elsewhere no longer opens.
 * Only vetted libsodium primitives are used; nothing here is a custom construction.
 *
 * @since  0.1.0
 */
final readonly class SodiumEnvelopeCipher implements EnvelopeCipher
{
    /**
     * Largest plaintext accepted, which keeps the resulting ciphertext inside the envelope's own bound.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MAXIMUM_PLAINTEXT_BYTES = 1_000_000;

    /**
     * Largest associated data accepted on either side.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MAXIMUM_ASSOCIATED_DATA_BYTES = 4096;

    /**
     * Bind the cipher to one key; the value has already proved its identifier and size.
     *
     * @param  KeyMaterial  $key  Key recorded in every envelope and matched on decryption.
     *
     * @since  0.1.0
     */
    public function __construct(private KeyMaterial $key)
    {
    }

    /**
     * Name the key this instance seals under and opens with.
     *
     * @return  string  Identifier only, safe to log.
     *
     * @since   0.1.0
     */
    public function keyId(): string
    {
        return $this->key->keyId;
    }

    /**
     * Seal a value under this instance's key with a nonce drawn fresh for this call.
     *
     * Both inputs are bounded first, so an oversized value is refused before any key material is touched, and
     * the plaintext bound is what keeps the resulting ciphertext inside the envelope's own limit.
     *
     * @param   string  $plaintext       Value to protect, at most `MAXIMUM_PLAINTEXT_BYTES`.
     * @param   string  $associatedData  Binding authenticated alongside the ciphertext but not stored, at most
     *          `MAXIMUM_ASSOCIATED_DATA_BYTES`; the same string must be supplied again to decrypt.
     *
     * @return  EncryptedEnvelope  Ciphertext, the nonce it was sealed with, and this instance's key identifier.
     *
     * @throws  InvalidInput      When the plaintext or the associated data exceeds its bound.
     * @throws  EncryptionFailed  When the random source cannot supply a nonce or libsodium refuses the encryption.
     *
     * @since   0.1.0
     */
    public function encrypt(#[SensitiveParameter] string $plaintext, string $associatedData): EncryptedEnvelope
    {
        if (strlen($plaintext) > self::MAXIMUM_PLAINTEXT_BYTES) {
            throw new InvalidInput('The secret value exceeds its safe bound.');
        }
        self::assertAssociatedDataBound($associatedData);
        try {
            $nonce = random_bytes(EncryptedEnvelope::NONCE_BYTES);
        } catch (RandomException) {
            throw new EncryptionFailed('The secret envelope nonce could not be generated.');
        }
        try {
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                $associatedData,
                $nonce,
                $this->key->material(),
            );
        } catch (SodiumException) {
            throw new EncryptionFailed('The secret envelope could not be sealed.');
        }

        return new EncryptedEnvelope($ciphertext, $nonce, $this->key->keyId);
    }

    /**
     * Open an envelope this instance's key sealed and hand back the value.
     *
     * The key identifier is compared first, in constant time, so an envelope from a rotated or foreign key fails
     * as an unavailable key instead of as a decryption error. Authentication then covers the ciphertext, the
     * nonce and the associated data together, and every way they can disagree is one indistinguishable refusal.
     *
     * @param   EncryptedEnvelope  $envelope        Stored ciphertext with its nonce and the identifier of the key
     *          that sealed it.
     * @param   string             $associatedData  The binding used at encryption time; any difference fails
     *          authentication.
     *
     * @return  string  The original plaintext, byte for byte.
     *
     * @throws  InvalidInput          When the associated data exceeds its bound.
     * @throws  KeyUnavailable        When the envelope names another key than the one this instance holds.
     * @throws  AuthenticationFailed  When libsodium refuses the input or the ciphertext fails authentication.
     *
     * @since   0.1.0
     */
    public function decrypt(EncryptedEnvelope $envelope, string $associatedData): string
    {
        if (!KeyIdentifier::equals($this->key->keyId, $envelope->keyId)) {
            throw new KeyUnavailable($envelope->keyId);
        }
        self::assertAssociatedDataBound($associatedData);
        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $envelope->ciphertext,
                $associatedData,
                $envelope->nonce,
                $this->key->material(),
            );
        } catch (SodiumException) {
            throw new AuthenticationFailed('The secret envelope failed authenticated decryption.');
        }
        if (!is_string($plaintext)) {
            throw new AuthenticationFailed('The secret envelope failed authenticated decryption.');
        }

        return $plaintext;
    }

    /**
     * Refuse associated data beyond the bound, on either side of the operation.
     *
     * @param   string  $associatedData  Binding supplied by the caller.
     *
     * @return  void
     *
     * @throws  InvalidInput  When the binding exceeds `MAXIMUM_ASSOCIATED_DATA_BYTES`.
     *
     * @since   0.1.0
     */
    private static function assertAssociatedDataBound(string $associatedData): void
    {
        if (strlen($associatedData) > self::MAXIMUM_ASSOCIATED_DATA_BYTES) {
            throw new InvalidInput('The associated data exceeds its safe bound.');
        }
    }
}
