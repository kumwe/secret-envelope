# Releasing Secret Envelope

Follow the [Package release standard](package-release-standard.md) for the shared
quality gate, changelog parsing, publication and retry behavior. Complete the
[repository release setup](repository-release-setup.md) with an administrator
session before merging a release record:

```bash
bash tools/configure-release-repositories.sh --check kumwe/secret-envelope
bash tools/configure-release-repositories.sh --apply kumwe/secret-envelope
```

The required CI check is **Package gate**. Maintainers rebase reviewed PRs into the
repository's current default branch; the release workflow reruns the same quality
gate on the resulting commit and derives its release identity from that run.
A release intention in CHANGELOG.md is not evidence that publication occurred.
Keep work that is not ready for publication under `## Unreleased`.

Pre-1.0 consumers pin exact verified versions. Public signatures, constants, formats,
refusal semantics and corpus changes require explicit review and a new version.
The package's PHP 8.5 lane retains Sodium and all declared platform requirements.

## Artifact and consumer verification

The built Composer archive is verified and installed as a dependency in a fresh
project with no development dependencies, no path repository and an authoritative
autoloader. Shipped examples and manifests are checked there. The optional Laminas
host integration is installed afterward and its aliases/lifetimes are exercised;
it cannot conceal a missing runtime dependency in the first stage.

The independent post-publication verification includes manifest/corpus digests,
registry availability, security, license, provenance and clean consumption. Its
external attestation and the shipped handoff are prerequisites for App adoption.
Withdraw unsafe coordinates through advisory/yank mechanisms where available,
release a corrected successor, and retain the last safe App pin. Assess stored
ciphertext compatibility, key retention and restore safety before rollback.

## Publication evidence and recovery

The maintainer performs the initial Packagist submission. Its GitHub integration
then follows tags without a registry credential in CI. Before dependent publication
or App adoption, a fresh independent verifier must bind the exact published
source/tag, archive digest, manifests, registry coordinate, license/security and
clean-consumer results in an external RELEASE-ATTESTATION.yaml. The artifact and
handoff must not invent their own final commit, checksum or publication evidence.

Use the current release workflow on the default branch to retry after correcting
repository settings. Historical mutable releases remain unchanged: enabling
immutability affects future publications, so a mutable version requires an unused
successor. Never move or delete a published tag or replace a released artifact.
An unpublished tag can be completed only on the exact commit tested by the retry.
A green PR does not replace the default-branch release result or independent
verification. Administrator credentials do not belong in Actions.
