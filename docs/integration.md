# Core contract and host integration

Direct construction needs only the declared runtime dependencies. Run `php examples/round-trip.php` and
`php examples/rotation.php` after Composer installation. A provider over a `KeyRing` serves new writes from its
active key and reads from its active or retained keys; it never tries alternate keys after failure.

For explicit Laminas service construction, require `laminas/laminas-servicemanager:^4.5` in the host and run
`php examples/container.php`. The domain has no framework dependency. Applications using a ConfigAggregator add
`Kumwe\Secret\ConfigProvider::class` to the explicit provider list and merge their `KeyProvider::class` binding
before resolving `EnvelopeCipher::class`.

The provider registers `Cipher\KeyRingEnvelopeCipher` with `Container\KeyRingEnvelopeCipherFactory`, aliases
`Contract\EnvelopeCipher` to that service, and makes it shared. The factory input is the host `KeyProvider`.
No configuration key, ambient secret, default key source or autowiring is used.

One provider describes one purpose. A host needing record and token ciphers constructs separate rings/ciphers
and injects them into appropriate consumers explicitly. Do not bind both purposes to the one global alias.
Keep App HKDF labels, active identifiers, retired-key inventory and associated-data markers exact; changing
them can strand existing ciphertext. Deploy required keys before consuming migrated code.

The factory throws `ServiceBindingRefused` for a wrong binding and propagates PSR-11 missing-service errors.
A provider must return matching active identifiers/material stable per request/job, throw `KeyUnavailable`
for unavailable/revoked keys and keep I/O latency bounded. Non-exportable HSM keys need a host implementation
of `EnvelopeCipher`; `KeyProvider` explicitly supplies exportable derived key bytes.

Persist only `EncryptedEnvelope::toStorage()` and use `fromStorage()` on reads. Recompute associated data from
trusted host context. The package makes no authorization decision and records no audit. Update host exception
catches to canonical package types and retain rotation, restore and audit tests.

## Compatibility and test ownership

The [source inventory](extraction-inventory.json) records an exact historical Core baseline. Check current
consumers and test responsibilities before replacing old types. Keep SecretKeyPurpose, derivation labels,
associated-data domain markers and record/mutation-plan cipher bindings under host ownership.

Package tests own envelope formats, key/ring invariants, cryptographic refusals and service behavior.
Core retains custody/derivation, per-purpose separation, cross-record binding, rotation, restore, audit,
database and CLI tests. Split mixed tests and remove duplicate implementation-only tests together with
their retired implementation after verified adoption. Keep unchanged external artifact evidence.
