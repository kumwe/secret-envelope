# Security and compatibility boundaries

Only libsodium XChaCha20-Poly1305 is used. Each encryption draws a fresh random 192-bit nonce. Keys are derived
256-bit AEAD keys; master secrets and HKDF derivation remain in host custody. No plaintext or key bytes appear
in package errors or chained engine exceptions. `SensitiveParameter` inputs are redacted in PHP traces.
Never log caller variables, reflection output or `var_export()` of a key-bearing graph.

`KeyMaterial` redacts ordinary debug output and refuses serialization. PHP immutable strings and copies mean
reliable zeroization is not promised. Serialized objects or untrusted PHP objects are not input formats; use
the validated storage array. Host serializers must never bypass constructors or deserialize hostile objects.

Authentication covers ciphertext, nonce and supplied associated data. The fixed algorithm is validated before
use; key identifiers select exactly one key. Never reuse an identifier for different material, share material
across purpose rings or derive two purposes with one label. Purpose mismatch fails by unavailable identity
or authentication. The algorithm spelling fixes the format; unknown algorithms are refused.

Unknown keys are distinct from authentication failure, which is indistinguishable across tampering causes.
Identifiers use constant-time equality where compared, but length and retired-key lookup timing are not secret.
Provider failures propagate fail-closed and must obey the port's secret-free error contract. Remote provider
exception hygiene is a host responsibility.

The corpus uses openly documented test keys and fixed nonces only as compatibility vectors. Production never
reuses those keys or supplies a nonce. Property tests also exercise random fresh nonces and hostile envelopes.

Report suspected security defects through private vulnerability reporting when available or directly to the
maintainer. Do not post real keys or plaintext in public issues. Compromised keys require host revocation,
replacement deployment, audited re-encryption and restoration ordering; dependency rollback is not key recovery.
