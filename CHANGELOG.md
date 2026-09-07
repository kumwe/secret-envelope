# Changelog

The newest release heading is authoritative. A human merge to main triggers the complete release gate before
automation may create its tag and GitHub release. No release is claimed by this record alone.

## 0.1.0

- Complete the interrupted extraction under the canonical `Kumwe\Secret` namespace with its architecture,
  public API, capability and service manifests, reproducible examples, versioned crypto corpus and handoff.
- Preserve authenticated XChaCha20-Poly1305 envelope storage and host ownership of custody, purpose selection,
  derivation, persistence, rotation, authorization and audit. The package adds typed errors, redaction,
  serialization refusal, bounds and explicit provider construction.
- Correct numeric retired key identifiers being exported as integers by PHP array-key coercion.
- Bound encoded ciphertext and nonce sizes before allocating decoded storage input.
- Add complete package checks and isolated Composer archive consumption, including no-dev authoritative
  autoloading and separately verified Laminas service resolution. Human merge and independent release
  verification remain required before App adoption (`KUMWE-MIG-2026-003`).
