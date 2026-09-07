# Release protocol

The newest `## X.Y.Z` heading in CHANGELOG.md is the release record. Unreleased headings may precede it;
malformed release headings fail closed. Pre-1.0 consumers pin exact verified versions. Public signatures,
constants, formats, refusal semantics and corpus changes require explicit review and a new version.

`composer check` is the single local/CI gate. CI runs the PHP 8.5 platform matrix with Sodium, read-only
repository access and commit-pinned actions. Release-on-record runs after pushes to main, serializes without
cancellation and re-proves the same full gate before its separate write job.

## Repository setup and recovery

Packagist's GitHub integration follows published tags; it does not configure GitHub's release prerequisites.
Before merging a release record, a repository administrator must:

1. Protect `main` using an active branch ruleset in
   [Settings > Rules > Rulesets](https://github.com/kumwe/secret-envelope/settings/rules), or a classic branch
   protection rule. Target `main`, require pull requests and the `PHP 8.5` check, and block deletion and force
   pushes. The release workflow uses the distinct `Release gate PHP 8.5` name so the required CI check is
   unambiguous. An evaluation-only or disabled ruleset does not enforce protection.
2. Open [Settings > General](https://github.com/kumwe/secret-envelope/settings), scroll to **Releases**, and
   enable **release immutability**, or confirm an enforced organization policy covers this repository.
   GitHub documents that [this only applies to future releases](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/establish-provenance-and-integrity/prevent-release-changes).

The September 7 failure in [run 34128853927](https://github.com/kumwe/secret-envelope/actions/runs/34128853927)
occurred after all PHP/package checks passed: `github.ref_protected` was `false`. The next check would also
have refused the already-published `v0.1.0`, whose API metadata reports `immutable: false`. That historical
metadata does not establish whether immutability is enabled now, and enabling it cannot repair `v0.1.0`.
The `0.1.1` record selects a fresh coordinate for the successor, including the fixes made since `v0.1.0`.
Keep `v0.1.0` and its tag unchanged.

After the settings are correct, merge the successor PR normally. If a settings correction is needed after
merging, select **Actions > Release on record > Run workflow > main**. Manual dispatch runs the full package
gate again before release mutations; selecting another branch or a tag cannot enter either job. Do not just
rerun the historical `0.1.0` workflow: it still selects the old release record. If a newer mutable release has
already been published, correct the settings and record another successor rather than rewriting that release.

These settings require administrator access; the workflow's token deliberately has no administration permission.
A green pull-request package check therefore does not by itself prove that release
administration is configured. Protection and immutable-publication checks remain mandatory.

## Publication and consumption

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
