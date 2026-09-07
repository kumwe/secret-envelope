# Secret envelope charter

`kumwe/secret-envelope` owns portable authenticated encrypted envelopes, key identity/material/ring values,
the cipher and key-provider ports, the Sodium cipher and explicit factory configuration under `Kumwe\Secret`.

The host owns master secrets, key derivation and custody, environment/files/KMS/HSM integration, purpose
selection, authorization, database storage, audit, recovery, key retirement and re-encryption scheduling.
No App class, request, connection, principal, tenant or mutable operation context belongs in this package.

The runtime dependency ceiling is PHP 8.5, ext-sodium and PSR-11 for explicit container integration.
PSR-11 appears only in the factory and its container-binding refusal. Domain services are container-agnostic.
Laminas ServiceManager is a development verification dependency and an optional host integration choice.

The `EnvelopeCipher` and `KeyProvider` ports are replacement seams; values and implementations are final.
No historical namespace aliases, runtime reflection wiring, cryptographic fallback, custom cipher or
master-key getter is permitted. `KeyMaterial::material()` deliberately exposes only a derived AEAD key to
the cryptographic collaborator; callers must never put a deployment master secret into this value.

Package tests own format/invariant/cryptographic/service behavior. App owns composed rotation, restore,
authorization and audit behavior. App adoption requires an independently verified immutable release.
