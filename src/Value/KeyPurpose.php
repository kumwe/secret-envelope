<?php

declare(strict_types=1);

namespace Kumwe\Secret\Value;

use Kumwe\Secret\Exception\InvalidKeyMaterial;

/**
 * One reason a host holds key material, with the frozen coordinates that keep it separate from every other.
 *
 * Two very different things can be sealed with the same construction: durable stored fields that outlive every
 * process that wrote them, and short-lived tokens handed to a browser. Sharing one key makes their lifecycles one
 * lifecycle, so a purpose gives each its own derivation label and its own default key identifier: keys derived
 * for different purposes are unrelated bytes, and each purpose is served by its own `KeyRing`, so an envelope
 * from one purpose presented to another is refused as `KeyUnavailable` by identifier. Which purposes exist, and
 * how their keys are derived from configured secrets, is the host's key custody; this value only carries the
 * coordinates. A derivation label is frozen for the life of the envelopes sealed under it: changing it changes
 * every key derived from the same secret. A new derivation gets a new label and a new identifier instead.
 *
 * @since  0.1.0
 */
final readonly class KeyPurpose
{
    /**
     * Anchored grammar of a derivation label: printable, whitespace-free, bounded.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string LABEL_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,254}$/D';

    /**
     * Name a purpose and prove its coordinates are usable.
     *
     * @param   string  $name             Short name of the purpose, in the `KeyIdentifier` grammar; it labels
     *          rings and diagnostics and never reaches an envelope.
     * @param   string  $derivationLabel  Info string the host derives this purpose's key material under; it must
     *          differ from every other purpose's label and never change once envelopes exist.
     * @param   string  $defaultKeyId     Identifier stamped into envelopes when a deployment configures no
     *          identifier of its own, in the `KeyIdentifier` grammar.
     *
     * @throws  InvalidKeyMaterial  When the name or default identifier leaves the identifier grammar, or the label
     *          leaves `LABEL_PATTERN`.
     *
     * @since   0.1.0
     */
    public function __construct(
        public string $name,
        public string $derivationLabel,
        public string $defaultKeyId,
    ) {
        if (!KeyIdentifier::isValid($name)) {
            throw new InvalidKeyMaterial('A key purpose name is invalid.');
        }
        if (preg_match(self::LABEL_PATTERN, $derivationLabel) !== 1) {
            throw new InvalidKeyMaterial('A key purpose derivation label is invalid.');
        }
        if (!KeyIdentifier::isValid($defaultKeyId)) {
            throw new InvalidKeyMaterial('A key purpose default key identifier is invalid.');
        }
    }
}
