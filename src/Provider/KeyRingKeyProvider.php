<?php

declare(strict_types=1);

namespace Kumwe\Secret\Provider;

use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;

/**
 * The production-capable default `KeyProvider`: an in-process ring the host builds from its own custody.
 *
 * Key material arrives from the deployment, an environment variable, a mounted file, a secret store, is derived
 * by the host into per-purpose rings, and is held in memory for the life of the process. That satisfies every
 * clause of the adapter contract without a network round trip: the active identifier cannot change mid-request,
 * resolution is by identifier, and an identifier the ring does not hold fails closed.
 *
 * It is the reference implementation an external adapter is measured against rather than a placeholder. A KMS or
 * HSM adapter replaces this one class and nothing else; what it must additionally solve, caching, bounded
 * latency, revocation, is what a ring in memory gets for free.
 *
 * @since  0.1.0
 */
final readonly class KeyRingKeyProvider implements KeyProvider
{
    /**
     * Bind the provider to the ring it answers from.
     *
     * @param  KeyRing  $ring  Active key plus the retired keys this deployment still holds.
     *
     * @since  0.1.0
     */
    public function __construct(private KeyRing $ring)
    {
    }

    /**
     * Name the ring's active key.
     *
     * @return  string  Identifier stamped into every envelope sealed from now on.
     *
     * @since   0.1.0
     */
    public function activeKeyId(): string
    {
        return $this->ring->active->keyId;
    }

    /**
     * Produce the ring's active key.
     *
     * @return  KeyMaterial  The active key; a constructed ring always has one, so this never fails.
     *
     * @since   0.1.0
     */
    public function activeKey(): KeyMaterial
    {
        return $this->ring->active;
    }

    /**
     * Resolve the key an envelope names from the ring.
     *
     * @param   string  $keyId  Identifier read from the stored envelope.
     *
     * @return  KeyMaterial  The key that identifier names.
     *
     * @throws  KeyUnavailable  When the ring holds no such key.
     *
     * @since   0.1.0
     */
    public function keyFor(string $keyId): KeyMaterial
    {
        return $this->ring->keyFor($keyId);
    }

    /**
     * Name every key the ring can open an envelope with.
     *
     * @return  non-empty-list<string>  Active identifier first, then the retired ones in string order.
     *
     * @since   0.1.0
     */
    public function knownKeyIds(): array
    {
        return $this->ring->keyIds();
    }
}
