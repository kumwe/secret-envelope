# Changelog

The newest release heading is authoritative. A human merge to main triggers the complete release gate before
automation may create its tag and GitHub release. No release is claimed by this record alone.

## 0.1.1

- Unify PR and post-rebase release gates, dynamic release identity, tested publication retries,
  and administrator setup across the package family. Preserve immutable release and dependency evidence requirements.

- Enforce package-owned behavior, boundary and conformance evidence for every exported type, including
  encryption refusals and explicit container boundaries.
- Preserve native encryption success paths in isolated fault fixtures so refusal tests exercise the
  intended failure without replacing unrelated cryptographic behavior.
- Prepare a successor release for the protected-main and immutable-publication requirements. Keep the
  historical mutable v0.1.0 publication unchanged; release recovery requires the repository prerequisites
  and a newly tested release record.
- Keep the runtime API and stored envelope format unchanged; refresh the release metadata and handoff
  manifest digests for the successor candidate.

## 0.1.0

- Complete the interrupted extraction under the canonical `Kumwe\Secret` namespace with its architecture,
  public API, capability and service manifests, reproducible examples, versioned crypto corpus and handoff.
- Preserve authenticated XChaCha20-Poly1305 envelope storage and host ownership of custody, purpose selection,
  derivation, persistence, rotation, authorization and audit. The package adds typed errors, redaction,
  serialization refusal, bounds and explicit provider construction.
- Correct numeric retired key identifiers being exported as integers by PHP array-key coercion.
- Bound encoded ciphertext and nonce sizes before allocating decoded storage input.
- Make randomized disclosure assertions use a distinct plaintext sentinel so harmless one-byte matches in
  error messages cannot cause intermittent false failures.
- Add complete package checks and isolated Composer archive consumption, including no-dev authoritative
  autoloading and separately verified Laminas service resolution. Human merge and independent release
  verification remain required before App adoption (`KUMWE-MIG-2026-003`).
