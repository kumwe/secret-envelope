<?php

declare(strict_types=1);

namespace Kumwe\Secret\Value;

use Kumwe\Secret\Exception\InvalidKeyMaterial;
use Kumwe\Secret\Exception\SecretDisclosureRefused;
use SensitiveParameter;

/**
 * One AEAD key together with the identifier envelopes record it under.
 *
 * A key on its own cannot be rotated, because nothing sealed with it says so; pairing the bytes with a versioned
 * name is what lets a `KeyRing` hand back the right key for an envelope written years ago instead of trying keys
 * until one happens to authenticate. The bytes stay private and are reachable only through `material()`; the
 * constructor parameter is marked sensitive so a stack trace redacts it; `__debugInfo()` replaces it so a
 * `var_dump()` or `print_r()` cannot spill it; and `serialize()` is refused outright. `var_export()` and
 * reflection bypass every redaction PHP offers, so a host must never apply them to a key.
 *
 * @since  0.1.0
 */
final readonly class KeyMaterial
{
    /**
     * Exact key length of the construction, 256 bits.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int KEY_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    /**
     * Bind an identifier to its key and prove both halves are usable before anything is sealed.
     *
     * @param   string  $keyId     Name of the key, stamped into every envelope it seals, in the `KeyIdentifier`
     *          grammar.
     * @param   string  $material  Raw XChaCha20-Poly1305 key bytes of exactly `KEY_BYTES`: derived material,
     *          never a passphrase or its hexadecimal spelling.
     *
     * @throws  InvalidKeyMaterial  When the identifier does not match the grammar, or the key is not exactly the
     *          algorithm's key size. Neither message quotes the key.
     *
     * @since   0.1.0
     */
    public function __construct(
        public string $keyId,
        #[SensitiveParameter] private string $material,
    ) {
        if (!KeyIdentifier::isValid($keyId)) {
            throw new InvalidKeyMaterial('A secret encryption key identifier is invalid.');
        }
        if (strlen($material) !== self::KEY_BYTES) {
            throw new InvalidKeyMaterial('A secret encryption key has an invalid size.');
        }
    }

    /**
     * Hand the raw key bytes to the one collaborator entitled to them.
     *
     * Only a cipher calls this. Everything else works with the identifier, which is safe to log.
     *
     * @return  string  The raw key bytes, exactly as supplied.
     *
     * @since   0.1.0
     */
    public function material(): string
    {
        return $this->material;
    }

    /**
     * Present the key to a debugger with its bytes replaced.
     *
     * `var_dump()` on an object graph is one of the ways key material escapes into a log or a bug report, and
     * this is the hook that stops it: the identifier is disclosed, the key never is.
     *
     * @return  array{keyId: string, material: string}  The identifier, and a fixed redaction marker.
     *
     * @since   0.1.0
     */
    public function __debugInfo(): array
    {
        return ['keyId' => $this->keyId, 'material' => '[redacted]'];
    }

    /**
     * Refuse to serialize, because a serialized key has no redaction hook wherever it lands.
     *
     * @return  array<never, never>  Never returns; declared so PHP accepts the magic method signature.
     *
     * @throws  SecretDisclosureRefused  Always.
     *
     * @since   0.1.0
     */
    public function __serialize(): array
    {
        throw new SecretDisclosureRefused('Secret key material cannot be serialized.');
    }
}
