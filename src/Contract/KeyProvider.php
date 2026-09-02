<?php

declare(strict_types=1);

namespace Kumwe\Secret\Contract;

use Kumwe\Secret\Value\KeyMaterial;

/**
 * Port through which a cipher acquires key material, separate from the cipher itself.
 *
 * `EnvelopeCipher` says how bytes are sealed; this says where the key comes from. Splitting them is what lets a
 * host keep an in-process ring built from its configuration, or put a managed KMS or an HSM behind the same two
 * questions, which key should new writes use and can you produce the key this envelope names, without the code
 * that stores envelopes knowing which answer it is talking to.
 *
 * **Adapter contract.** An implementation owes the following:
 *
 * - *Identifier namespace.* `activeKeyId()` returns a stable, versioned name in the `KeyIdentifier` grammar. It
 *   is written into every envelope and is the only thing storage records about the key, so it must never be
 *   reused for different key material: a new key is a new identifier, always.
 * - *Stability within a process.* `activeKeyId()` and `activeKey()` must agree and must not change during one
 *   request or one job. A provider that rotates underneath a running batch would leave envelopes stamped with an
 *   identifier the bytes do not match.
 * - *Fail closed.* `keyFor()` raises `KeyUnavailable` for an identifier it cannot produce, including a revoked
 *   one, and never substitutes another key. Returning the wrong key would turn a recoverable "restore the key"
 *   into a silent authentication failure; the ring cipher additionally refuses a key whose identifier differs
 *   from the one it asked for.
 * - *Disclosure.* No implementation may log, print or attach key material to an exception, a metric or a trace.
 *   `KeyMaterial` redacts itself; an adapter must not undo that by logging the bytes it received before wrapping
 *   them.
 * - *Latency and caching.* Every write and every re-encryption calls `activeKey()`, so a remote provider must
 *   cache within the process and must bound its network wait; a provider that blocks makes writes block. A cache
 *   entry is dropped on revocation signalling, which for this port means the next `keyFor()` raising
 *   `KeyUnavailable`.
 * - *Audit.* Key use is not audited here; a host's own trail records the mutation, and its rotation pass records
 *   what it re-encrypted. An external provider is expected to keep its own access log.
 *
 * @since  0.1.0
 */
interface KeyProvider
{
    /**
     * Name the key every new envelope must be sealed under.
     *
     * @return  string  Identifier of the active key, in the `KeyIdentifier` grammar.
     *
     * @since   0.1.0
     */
    public function activeKeyId(): string;

    /**
     * Produce the key every new envelope must be sealed with.
     *
     * @return  KeyMaterial  Active key and its identifier, which must equal `activeKeyId()`.
     *
     * @throws  \Kumwe\Secret\Exception\KeyUnavailable  When the provider cannot produce its own active key, which
     *          is a deployment fault rather than a data fault and must stop writes rather than degrade them.
     *
     * @since   0.1.0
     */
    public function activeKey(): KeyMaterial;

    /**
     * Produce the key one stored envelope names.
     *
     * @param   string  $keyId  Identifier read from the envelope, which may name a retired key.
     *
     * @return  KeyMaterial  The key that identifier names.
     *
     * @throws  \Kumwe\Secret\Exception\KeyUnavailable  When the identifier names a key this provider does not
     *          hold, has retired, or has had revoked.
     *
     * @since   0.1.0
     */
    public function keyFor(string $keyId): KeyMaterial;

    /**
     * Name every key this provider can currently open an envelope with.
     *
     * Operators read this before and after a rotation to confirm that a retired key is still loaded, and that it
     * has been dropped once nothing references it. A provider that cannot enumerate its keys, as some KMS
     * deployments deliberately cannot, returns just the active identifier, which is honest: it says only that the
     * active key is present, not that no others are.
     *
     * @return  non-empty-list<string>  Identifiers, active first; names only, never material.
     *
     * @since   0.1.0
     */
    public function knownKeyIds(): array;
}
