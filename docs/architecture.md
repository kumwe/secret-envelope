# Architecture and ownership

The dependency direction is host custody → `KeyProvider` → `KeyRingEnvelopeCipher` → `SodiumEnvelopeCipher`.
Envelope and key values carry validated bytes/identifiers; the domain never receives a container. A single
explicit factory resolves only the canonical provider port, and `ConfigProvider` binds the cipher alias.

`EncryptedEnvelope` preserves the App storage format: ciphertext and nonce encoded as base64, `key_id`, then
`algorithm: xchacha20poly1305-ietf`. The algorithm fixes the construction; no custom cipher or fallback exists.
The associated-data domain is separately versioned by its caller and is authenticated, not stored.

Key-purpose values carry coordinates; App's closed `SecretKeyPurpose` cases and frozen HKDF labels remain
host policy. `SecretAssociatedData` remains a host field-coordinate policy wrapper that should delegate to
`AssociatedData::for()` with its unchanged `business-record-secret-v1` marker. Neither host policy is deleted.

`KeyMaterial` contains a derived 32-byte AEAD key, never the deployment master secret. Its `material()` method
is a cryptographic boundary; only ciphers or trusted custody adapters may call it. Zeroization cannot be
promised for immutable PHP strings. Debug output redacts and serialization refuses keys.

Package tests replace App's envelope, single-key cipher and portable ring unit cases after release adoption.
Configured custody/derivation tests, rotation/restore integration, CLI and persistence remain App tests.
See the handoff for source paths, consumers and exact split instructions.

Intentional extraction corrections include typed SPL-compatible refusals, refusing key serialization,
validating provider-returned key identity, input bounds on both cipher directions, rejecting newline ambiguity
in associated-data coordinates, numeric identifier type preservation and checking encoded sizes before decode.
The single-key cipher accepts `KeyMaterial` instead of two raw constructor strings. Valid stored ciphertext
remains compatible. These changes are recorded in the initial 0.1.0 API and its deterministic corpus.
