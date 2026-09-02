<?php

declare(strict_types=1);

namespace Kumwe\Secret\Value;

use Kumwe\Secret\Exception\InvalidEnvelope;

/**
 * A sealed value together with everything needed to open it again, and nothing that would open it.
 *
 * The envelope is the versioned storage format of the package: the raw AEAD output with its authentication tag,
 * the nonce it was sealed under, the identifier of the key that sealed it and the name of the construction.
 * Naming the key and the construction alongside the ciphertext is what makes rotation survivable, because a
 * decrypt against the wrong key is refused by identifier rather than mistaken for corrupt data, and it is what
 * stops a downgraded stored row from loading. The constructor is the single gate: an envelope rebuilt from a
 * stored row is validated exactly as one produced by a cipher. The value carries no plaintext and no key.
 *
 * @since  0.1.0
 */
final readonly class EncryptedEnvelope
{
    /**
     * The only AEAD construction envelopes may be sealed with, in its portable spelling.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string ALGORITHM = 'xchacha20poly1305-ietf';

    /**
     * Shortest ciphertext the construction can produce: the authentication tag of an empty plaintext.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MINIMUM_CIPHERTEXT_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

    /**
     * Largest ciphertext an envelope admits, one mebibyte, which bounds what a stored row can make a reader load.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MAXIMUM_CIPHERTEXT_BYTES = 1_048_576;

    /**
     * Exact nonce length of the construction, 192 bits.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    /**
     * Assemble an envelope and prove each part is well formed before it can be stored or opened.
     *
     * @param   string  $ciphertext  Raw sealed bytes including the authentication tag; at least the tag length
     *          and at most `MAXIMUM_CIPHERTEXT_BYTES`.
     * @param   string  $nonce       Raw nonce bytes, exactly `NONCE_BYTES` long.
     * @param   string  $keyId       Identifier of the key the ciphertext was sealed with, in the
     *          `KeyIdentifier` grammar, so a rotated key is recognised instead of silently mis-applied.
     * @param   string  $algorithm   Construction the ciphertext was produced with; anything other than
     *          `ALGORITHM` is refused, which is what stops a downgraded stored row from loading.
     *
     * @throws  InvalidEnvelope  When the ciphertext is too short or oversized, the nonce is the wrong length, the
     *          key identifier is malformed, or the algorithm is unsupported. No message quotes the bytes.
     *
     * @since   0.1.0
     */
    public function __construct(
        public string $ciphertext,
        public string $nonce,
        public string $keyId,
        public string $algorithm = self::ALGORITHM,
    ) {
        $length = strlen($ciphertext);
        if ($length < self::MINIMUM_CIPHERTEXT_BYTES) {
            throw new InvalidEnvelope('An encrypted envelope ciphertext is shorter than its authentication tag.');
        }
        if ($length > self::MAXIMUM_CIPHERTEXT_BYTES) {
            throw new InvalidEnvelope('An encrypted envelope ciphertext exceeds its bound.');
        }
        if (strlen($nonce) !== self::NONCE_BYTES) {
            throw new InvalidEnvelope('An encrypted envelope nonce has an invalid size.');
        }
        if (!KeyIdentifier::isValid($keyId)) {
            throw new InvalidEnvelope('An encrypted envelope key identifier is invalid.');
        }
        if ($algorithm !== self::ALGORITHM) {
            throw new InvalidEnvelope('An encrypted envelope algorithm is unsupported.');
        }
    }

    /**
     * Export the envelope in its deterministic, text-safe storage shape.
     *
     * The two byte strings are base64-encoded because raw AEAD output is not valid UTF-8 and could not be
     * JSON-encoded; the identifier and the algorithm travel verbatim. The key order and encoding are fixed, so
     * the same envelope always produces the same row, which is what lets a host checksum or compare it.
     *
     * @return  array{ciphertext: string, nonce: string, key_id: string, algorithm: string}  Byte strings
     *          base64-encoded, key identifier and algorithm verbatim, in this key order.
     *
     * @since   0.1.0
     */
    public function toStorage(): array
    {
        return [
            'ciphertext' => base64_encode($this->ciphertext),
            'nonce' => base64_encode($this->nonce),
            'key_id' => $this->keyId,
            'algorithm' => $this->algorithm,
        ];
    }

    /**
     * Rebuild an envelope from its storage row, decoding the two base64 byte strings strictly.
     *
     * The row is treated as hostile: a missing or non-string member, padding or alphabet damage in a byte
     * string, and every constructor refusal are all reported here rather than surfacing later as a failed
     * decryption. Members the row carries beyond the four known ones are ignored.
     *
     * @param   array<string, mixed>  $storage  Row as written by `toStorage()`.
     *
     * @return  self  An envelope that has passed the same checks as a freshly sealed one.
     *
     * @throws  InvalidEnvelope  When a member is missing or not a string, either byte string is not valid base64,
     *          or the decoded envelope fails construction.
     *
     * @since   0.1.0
     */
    public static function fromStorage(array $storage): self
    {
        $members = [];
        foreach (['ciphertext', 'nonce', 'key_id', 'algorithm'] as $member) {
            $value = $storage[$member] ?? null;
            if (!is_string($value)) {
                throw new InvalidEnvelope(sprintf('An encrypted envelope storage row has no "%s" member.', $member));
            }
            $members[$member] = $value;
        }
        $ciphertext = base64_decode($members['ciphertext'], true);
        $nonce = base64_decode($members['nonce'], true);
        if (!is_string($ciphertext) || !is_string($nonce)) {
            throw new InvalidEnvelope('An encrypted envelope contains invalid base64 data.');
        }

        return new self($ciphertext, $nonce, $members['key_id'], $members['algorithm']);
    }
}
