# Release protocol

The newest `## X.Y.Z` heading in CHANGELOG.md is the release record. Unreleased headings may precede it;
malformed release headings fail closed. Pre-1.0 consumers pin exact verified versions. Public signatures,
constants, formats, refusal semantics and corpus changes require explicit review and a new version.

`composer check` is the single local/CI gate. CI runs the PHP 8.5 platform matrix with Sodium, read-only
repository access and commit-pinned actions. Release-on-record runs after pushes to main, serializes without
cancellation and re-proves the same full gate before its separate write job.

Only confirmed HTTP 404 probes permit creation. Existing tags must identify a release-record commit in main
history; an unpublished tag must identify the exact newly tested commit. Existing publications are untouched.
Automation creates the lightweight tag at the merged commit and publishes the GitHub release. Agents never
merge, tag or publish. Packagist needs one maintainer submission, then follows tags without workflow credentials.

The built Composer archive is verified and installed into a fresh root project with `--no-dev`, no package
development dependencies, no path repository and an authoritative autoloader. Shipped examples and manifests
are checked there. Afterwards the optional Laminas host integration is installed and aliases/lifetimes are
exercised; it cannot conceal a missing runtime dependency in the first stage.

An independent post-publication session verifies source/tag/archive identity, manifest/corpus digests,
registry availability, security/license/provenance and clean consumption, and writes an external attestation.
The artifact never carries its own final digest or publication claim. That attestation and the shipped handoff
are prerequisites for App adoption.

Do not move or overwrite tags. Withdraw unsafe coordinates through advisory/yank mechanisms where available,
release a corrected successor and keep the last safe App pin. Stored ciphertext compatibility, key retention
and restore safety must be assessed before rollback.
