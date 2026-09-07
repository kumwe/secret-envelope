---
schema: kumwe-migration-handoff/v2
artifact_kind: framework_php
migration_id: KUMWE-MIG-2026-003
change_set: KUMWE-CS-2026-003
state: draft_pr_open
source:
  app:
    repository: https://github.com/kumwe/app
    baseline_commit: "960ce8ec00cf724a7cae03e5ba09c4852c9ab54e"
    examined_paths:
      - "composer.json"
      - "composer.lock"
      - "docs/architecture/capability-index.md"
      - "src/BusinessRecord/Application/RecordSecretRotation.php"
      - "src/BusinessRecord/Application/RecordValueCodec.php"
      - "src/BusinessRecord/Application/SecretAssociatedData.php"
      - "src/BusinessRecord/Application/SecretCipher.php"
      - "src/BusinessRecord/Application/SecretKeyProvider.php"
      - "src/BusinessRecord/Domain/EncryptedEnvelope.php"
      - "src/BusinessRecord/Domain/RecordValueGuard.php"
      - "src/BusinessRecord/Domain/SecretKeyMaterial.php"
      - "src/BusinessRecord/Domain/SecretKeyPurpose.php"
      - "src/BusinessRecord/Domain/SecretKeyRing.php"
      - "src/BusinessRecord/Domain/SecretKeyUnavailable.php"
      - "src/BusinessRecord/Infrastructure/Persistence/DoctrineRecordSecretRotation.php"
      - "src/BusinessRecord/Infrastructure/Security/ConfiguredSecretKeyRings.php"
      - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretCipher.php"
      - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretKeyProvider.php"
      - "src/BusinessRecord/Infrastructure/Security/SodiumSecretCipher.php"
      - "src/BusinessSurface/Application/BusinessMutationPlanService.php"
      - "src/BusinessSurface/Application/MutationPlanCipher.php"
      - "src/BusinessSurface/Infrastructure/Security/KeyRingMutationPlanCipher.php"
      - "src/Identity/Application/StepUp/StepUpSecretCipher.php"
      - "src/Identity/Application/StepUp/TotpStepUpProvider.php"
      - "src/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipher.php"
      - "src/Kernel/Configuration/RecordEncryptionConfiguration.php"
      - "src/Kernel/ContainerFactory.php"
      - "tests/Architecture/MoneyConversionBoundaryTest.php"
      - "tests/Architecture/ProductionArtifactsTest.php"
      - "tests/Architecture/UnitConversionBoundaryTest.php"
      - "tests/Deployment/record-key-rotation-strand.php"
      - "tests/Integration/BusinessRecord/RecordSecretRotationIntegrationTest.php"
      - "tests/Support/BusinessRuntimeBackupAcceptance.php"
      - "tests/Support/RestoreSecurityAcceptance.php"
      - "tests/Unit/BusinessRecord/Application/AggregateInvariantValidationTest.php"
      - "tests/Unit/BusinessRecord/Application/BusinessRecordRelationshipCoordinatorTest.php"
      - "tests/Unit/BusinessRecord/Application/PostingPeriodLockTest.php"
      - "tests/Unit/BusinessRecord/Application/RecordRuleValidatorTest.php"
      - "tests/Unit/BusinessRecord/Domain/EncryptedEnvelopeTest.php"
      - "tests/Unit/BusinessRecord/Domain/ExactValueCodecTest.php"
      - "tests/Unit/BusinessRecord/Domain/RecordIntegrityTest.php"
      - "tests/Unit/BusinessRecord/Infrastructure/SecretKeyLifecycleTest.php"
      - "tests/Unit/BusinessRecord/Infrastructure/SodiumSecretCipherTest.php"
      - "tests/Unit/BusinessSurface/Application/BusinessMutationPlanServiceTest.php"
      - "tests/Unit/Identity/Application/StepUp/TotpStepUpProviderTest.php"
      - "tests/Unit/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipherTest.php"
    old_namespace_roots:
      - Kumwe\App\BusinessRecord\
    capability_index_sha256: "8fb2a8680bed6ac1456183bc9e48fe040194923b6d1b3331f04cea28bd5a9b2f"
  semantic_inputs: []
  examined_dependencies:
    - "App composer.lock and the installed legacy conversion, extension-sdk and producer capability boundaries"
    - "No Kumwe dependency selected; Sodium owns the cryptographic primitive; PSR-11 only binds host services"
  active_related_pull_requests: []
target:
  repository: https://github.com/kumwe/secret-envelope
  artifact_identity: kumwe/secret-envelope
  canonical_namespace_or_abi: Kumwe\Secret
  branch: fix/complete-extraction-release-gates
  pull_request: "https://github.com/kumwe/secret-envelope/pull/2"
ownership:
  responsibility: "Portable authenticated encrypted-envelope format, key values and cipher/provider contracts."
  non_responsibilities:
    - "Master-key custody, derivation, environment/KMS/HSM access, host purpose selection and authorization"
    - "Persistence, rotation/re-encryption jobs, audit, recovery, CLI/HTTP operations and deployment"
  allowed_dependency_ceiling:
    - "ext-sodium"
    - "psr/container"
  implementation_owner: kumwe/secret-envelope
  next_consumer: kumwe/app
  public_manifests:
    - path: resources/public-api/v1.json
      sha256: "21bed5fdd15b8e205f3881df1e41bd13df5b969ea13307ef645112b6fd56dd65"
    - path: resources/capabilities/v1.json
      sha256: "fb927883d0cfaef771eb02b83fe771b1344349ad2ae5737ec2cdc327f2e8f38e"
    - path: resources/service-map/v1.json
      sha256: "742e7619b6fd94dbea2595794b9d81cf5e270030ad732b6affa96d3e11a8e2bf"
  intentionally_excluded:
    - "src/BusinessRecord/Domain/SecretKeyPurpose.php remains host policy; update canonical imports/delegation only."
    - "src/BusinessRecord/Application/SecretAssociatedData.php remains host policy; update canonical imports/delegation only."
    - "src/BusinessRecord/Infrastructure/Security/ConfiguredSecretKeyRings.php remains host policy; update canonical imports/delegation only."
    - "src/BusinessSurface/Infrastructure/Security/KeyRingMutationPlanCipher.php remains host policy; update canonical imports/delegation only."
framework_php:
  composer_package: kumwe/secret-envelope
  canonical_namespace: Kumwe\Secret
  public_api_manifest: resources/public-api/v1.json
  capability_manifest: resources/capabilities/v1.json
  service_map: resources/service-map/v1.json
  extracted_symbols:
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Domain\\EncryptedEnvelope"
      new_fqcn: "Kumwe\\Secret\\Value\\EncryptedEnvelope"
      source_path: "src/BusinessRecord/Domain/EncryptedEnvelope.php"
      target_path: "src/Value/EncryptedEnvelope.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "fromStorage"
        - "toStorage"
      public_properties:
        - "algorithm"
        - "ciphertext"
        - "keyId"
        - "nonce"
      public_constants:
        - "ALGORITHM"
        - "MAXIMUM_CIPHERTEXT_BYTES"
        - "MINIMUM_CIPHERTEXT_BYTES"
        - "NONCE_BYTES"
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: "Base64 ciphertext, nonce, key_id and fixed algorithm preserved."
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyMaterial"
      new_fqcn: "Kumwe\\Secret\\Value\\KeyMaterial"
      source_path: "src/BusinessRecord/Domain/SecretKeyMaterial.php"
      target_path: "src/Value/KeyMaterial.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "__debugInfo"
        - "__serialize"
        - "material"
      public_properties:
        - "keyId"
      public_constants:
        - "KEY_BYTES"
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyRing"
      new_fqcn: "Kumwe\\Secret\\Value\\KeyRing"
      source_path: "src/BusinessRecord/Domain/SecretKeyRing.php"
      target_path: "src/Value/KeyRing.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "keyFor"
        - "keyIds"
      public_properties:
        - "active"
      public_constants:
        - "MAXIMUM_RETIRED_KEYS"
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyUnavailable"
      new_fqcn: "Kumwe\\Secret\\Exception\\KeyUnavailable"
      source_path: "src/BusinessRecord/Domain/SecretKeyUnavailable.php"
      target_path: "src/Exception/KeyUnavailable.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "keyId"
      public_properties: []
      public_constants: []
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Application\\SecretCipher"
      new_fqcn: "Kumwe\\Secret\\Contract\\EnvelopeCipher"
      source_path: "src/BusinessRecord/Application/SecretCipher.php"
      target_path: "src/Contract/EnvelopeCipher.php"
      kind: "interface"
      public_methods:
        - "decrypt"
        - "encrypt"
      public_properties: []
      public_constants: []
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Application\\SecretKeyProvider"
      new_fqcn: "Kumwe\\Secret\\Contract\\KeyProvider"
      source_path: "src/BusinessRecord/Application/SecretKeyProvider.php"
      target_path: "src/Contract/KeyProvider.php"
      kind: "interface"
      public_methods:
        - "activeKey"
        - "activeKeyId"
        - "keyFor"
        - "knownKeyIds"
      public_properties: []
      public_constants: []
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\SodiumSecretCipher"
      new_fqcn: "Kumwe\\Secret\\Cipher\\SodiumEnvelopeCipher"
      source_path: "src/BusinessRecord/Infrastructure/Security/SodiumSecretCipher.php"
      target_path: "src/Cipher/SodiumEnvelopeCipher.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "decrypt"
        - "encrypt"
        - "keyId"
      public_properties: []
      public_constants:
        - "MAXIMUM_ASSOCIATED_DATA_BYTES"
        - "MAXIMUM_PLAINTEXT_BYTES"
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\KeyRingSecretCipher"
      new_fqcn: "Kumwe\\Secret\\Cipher\\KeyRingEnvelopeCipher"
      source_path: "src/BusinessRecord/Infrastructure/Security/KeyRingSecretCipher.php"
      target_path: "src/Cipher/KeyRingEnvelopeCipher.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "decrypt"
        - "encrypt"
      public_properties: []
      public_constants: []
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
    - old_fqcn: "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\KeyRingSecretKeyProvider"
      new_fqcn: "Kumwe\\Secret\\Provider\\KeyRingKeyProvider"
      source_path: "src/BusinessRecord/Infrastructure/Security/KeyRingSecretKeyProvider.php"
      target_path: "src/Provider/KeyRingKeyProvider.php"
      kind: "class"
      public_methods:
        - "__construct"
        - "activeKey"
        - "activeKeyId"
        - "keyFor"
        - "knownKeyIds"
      public_properties: []
      public_constants: []
      exceptions:
        - "Kumwe\\Secret\\Exception\\SecretException"
      serialization_contract: null
      compatibility: "Namespace migration; typed errors and documented validation hardening; see architecture decisions."
  consumers:
    app_code:
      - "src/BusinessRecord/Application/SecretAssociatedData.php"
      - "src/BusinessRecord/Application/SecretCipher.php"
      - "src/BusinessRecord/Application/RecordSecretRotation.php"
      - "src/BusinessRecord/Application/RecordValueCodec.php"
      - "src/BusinessRecord/Application/SecretKeyProvider.php"
      - "src/Kernel/Configuration/RecordEncryptionConfiguration.php"
      - "src/Kernel/ContainerFactory.php"
      - "src/BusinessRecord/Infrastructure/Persistence/DoctrineRecordSecretRotation.php"
      - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretCipher.php"
      - "src/BusinessRecord/Infrastructure/Security/ConfiguredSecretKeyRings.php"
      - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretKeyProvider.php"
      - "src/BusinessRecord/Infrastructure/Security/SodiumSecretCipher.php"
      - "src/BusinessRecord/Domain/SecretKeyUnavailable.php"
      - "src/BusinessRecord/Domain/SecretKeyRing.php"
      - "src/BusinessRecord/Domain/SecretKeyPurpose.php"
      - "src/BusinessRecord/Domain/EncryptedEnvelope.php"
      - "src/BusinessRecord/Domain/SecretKeyMaterial.php"
      - "src/BusinessRecord/Domain/RecordValueGuard.php"
      - "src/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipher.php"
      - "src/Identity/Application/StepUp/StepUpSecretCipher.php"
      - "src/Identity/Application/StepUp/TotpStepUpProvider.php"
      - "src/BusinessSurface/Application/MutationPlanCipher.php"
      - "src/BusinessSurface/Application/BusinessMutationPlanService.php"
      - "src/BusinessSurface/Infrastructure/Security/KeyRingMutationPlanCipher.php"
    configuration_and_di:
      - "src/Kernel/ContainerFactory.php"
    reflection_and_string_references: []
    fixtures_and_examples:
      - "tests/Integration/BusinessRecord/RecordSecretRotationIntegrationTest.php"
      - "tests/Architecture/ProductionArtifactsTest.php"
      - "tests/Architecture/MoneyConversionBoundaryTest.php"
      - "tests/Architecture/UnitConversionBoundaryTest.php"
      - "tests/Unit/BusinessSurface/Application/BusinessMutationPlanServiceTest.php"
      - "tests/Deployment/record-key-rotation-strand.php"
      - "tests/Unit/Identity/Application/StepUp/TotpStepUpProviderTest.php"
      - "tests/Unit/BusinessRecord/Application/PostingPeriodLockTest.php"
      - "tests/Unit/BusinessRecord/Application/RecordRuleValidatorTest.php"
      - "tests/Unit/BusinessRecord/Application/BusinessRecordRelationshipCoordinatorTest.php"
      - "tests/Unit/BusinessRecord/Application/AggregateInvariantValidationTest.php"
      - "tests/Unit/BusinessRecord/Infrastructure/SecretKeyLifecycleTest.php"
      - "tests/Unit/BusinessRecord/Infrastructure/SodiumSecretCipherTest.php"
      - "tests/Unit/BusinessRecord/Domain/EncryptedEnvelopeTest.php"
      - "tests/Unit/BusinessRecord/Domain/RecordIntegrityTest.php"
      - "tests/Unit/BusinessRecord/Domain/ExactValueCodecTest.php"
      - "tests/Unit/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipherTest.php"
      - "tests/Support/BusinessRuntimeBackupAcceptance.php"
      - "tests/Support/RestoreSecurityAcceptance.php"
    external: []
  dependency_injection:
    mode: config-provider
    provider: Kumwe\Secret\ConfigProvider
    factories:
      - "Kumwe\\Secret\\Container\\KeyRingEnvelopeCipherFactory"
    aliases:
      - "Kumwe\\Secret\\Contract\\EnvelopeCipher -> Kumwe\\Secret\\Cipher\\KeyRingEnvelopeCipher"
    service_lifetimes:
      - "KeyRingEnvelopeCipher shared; host KeyProvider stable per request/job"
    configuration_keys: []
    provider_absence_reason: null
native_cpp: null
php_extension: null
tests:
  moved_or_added:
    - "tests/run.php"
    - "tests/TestCase.php"
    - "tests/Support/ScriptedServiceNotFound.php"
    - "tests/Support/ScriptedKeyProvider.php"
    - "tests/Support/Fixture.php"
    - "tests/Support/ScriptedContainer.php"
    - "tests/Case/KeyRingTest.php"
    - "tests/Case/ConfigProviderTest.php"
    - "tests/Case/SodiumEnvelopeCipherTest.php"
    - "tests/Case/ServiceResolutionTest.php"
    - "tests/Case/PropertyTest.php"
    - "tests/Case/KeyRingEnvelopeCipherFactoryTest.php"
    - "tests/Case/KeyPurposeTest.php"
    - "tests/Case/KeyMaterialTest.php"
    - "tests/Case/EncryptedEnvelopeTest.php"
    - "tests/Case/KeyRingKeyProviderTest.php"
    - "tests/Case/CorpusTest.php"
    - "tests/Case/KeyIdentifierTest.php"
    - "tests/Case/AssociatedDataTest.php"
    - "tests/Case/KeyRingEnvelopeCipherTest.php"
  remain_in_app_or_consumer:
    - "tests/Integration/BusinessRecord/RecordSecretRotationIntegrationTest.php"
    - "tests/Architecture/ProductionArtifactsTest.php"
    - "tests/Architecture/MoneyConversionBoundaryTest.php"
    - "tests/Architecture/UnitConversionBoundaryTest.php"
    - "tests/Unit/BusinessSurface/Application/BusinessMutationPlanServiceTest.php"
    - "tests/Deployment/record-key-rotation-strand.php"
    - "tests/Unit/Identity/Application/StepUp/TotpStepUpProviderTest.php"
    - "tests/Unit/BusinessRecord/Application/PostingPeriodLockTest.php"
    - "tests/Unit/BusinessRecord/Application/RecordRuleValidatorTest.php"
    - "tests/Unit/BusinessRecord/Application/BusinessRecordRelationshipCoordinatorTest.php"
    - "tests/Unit/BusinessRecord/Application/AggregateInvariantValidationTest.php"
    - "tests/Unit/BusinessRecord/Infrastructure/SecretKeyLifecycleTest.php"
    - "tests/Unit/BusinessRecord/Domain/RecordIntegrityTest.php"
    - "tests/Unit/BusinessRecord/Domain/ExactValueCodecTest.php"
    - "tests/Unit/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipherTest.php"
    - "tests/Support/BusinessRuntimeBackupAcceptance.php"
    - "tests/Support/RestoreSecurityAcceptance.php"
  split_tests:
    - "SecretKeyLifecycleTest retains configured custody/derivation, rotation and purpose integration; remove direct key/ring units."
  prohibited_duplicates:
    - "tests/Unit/BusinessRecord/Domain/EncryptedEnvelopeTest.php"
    - "tests/Unit/BusinessRecord/Infrastructure/SodiumSecretCipherTest.php"
  corpora:
    - "resources/corpus/v1/envelopes.json; publicly documented synthetic keys; SHA-256 865deefd162de7557ad5dbb3c95b40ae54bcb64a3b139112f0364d417e4d7260"
documentation:
  charter: CHARTER.md
  readme: README.md
  public_api: docs/public-api.md
  architecture: docs/architecture.md
  integration_or_consumer: docs/integration.md
  examples:
    - "examples/round-trip.php"
    - "examples/rotation.php"
    - "examples/container.php"
  changelog_record: "CHANGELOG.md ## 0.1.1"
release_expectations:
  version_policy: "SemVer; CHANGELOG.md selects 0.1.1; exact consumer pin after independent release verification."
  expected_artifact_types:
    - "Composer source ZIP"
    - "GitHub source archive"
  required_checks:
    - "composer check"
    - "PHP 8.5 CI"
    - "security audit"
    - "runtime-only archive consumer"
    - "Laminas consumer"
    - "Protected main and an immutable, stable, non-draft GitHub release of the exact recorded version"
  required_registry_or_installer: Packagist
  required_external_attestation: true
next_task:
  phase_name: "Independent release verification, then a separate App Phase 2 adoption"
  permitted_only_when:
    - "A human has merged the package PR and release-on-record has published the immutable release."
    - "A separate verifying session has produced a successful external RELEASE-ATTESTATION.yaml."
    - "Source/import/test drift against the recorded App baseline has been reconciled."
  consumer_repository: https://github.com/kumwe/app
  dependency_or_native_change: "Use Composer to require the exact verified release and regenerate composer.lock."
  namespace_or_api_replacements:
    - "Kumwe\\App\\BusinessRecord\\Domain\\EncryptedEnvelope -> Kumwe\\Secret\\Value\\EncryptedEnvelope"
    - "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyMaterial -> Kumwe\\Secret\\Value\\KeyMaterial"
    - "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyRing -> Kumwe\\Secret\\Value\\KeyRing"
    - "Kumwe\\App\\BusinessRecord\\Domain\\SecretKeyUnavailable -> Kumwe\\Secret\\Exception\\KeyUnavailable"
    - "Kumwe\\App\\BusinessRecord\\Application\\SecretCipher -> Kumwe\\Secret\\Contract\\EnvelopeCipher"
    - "Kumwe\\App\\BusinessRecord\\Application\\SecretKeyProvider -> Kumwe\\Secret\\Contract\\KeyProvider"
    - "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\SodiumSecretCipher -> Kumwe\\Secret\\Cipher\\SodiumEnvelopeCipher"
    - "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\KeyRingSecretCipher -> Kumwe\\Secret\\Cipher\\KeyRingEnvelopeCipher"
    - "Kumwe\\App\\BusinessRecord\\Infrastructure\\Security\\KeyRingSecretKeyProvider -> Kumwe\\Secret\\Provider\\KeyRingKeyProvider"
  files_to_update:
    - "composer.json"
    - "composer.lock"
    - "tests/Integration/BusinessRecord/RecordSecretRotationIntegrationTest.php"
    - "src/BusinessRecord/Application/SecretAssociatedData.php"
    - "src/BusinessRecord/Application/RecordSecretRotation.php"
    - "src/BusinessRecord/Application/RecordValueCodec.php"
    - "src/Kernel/Configuration/RecordEncryptionConfiguration.php"
    - "src/Kernel/ContainerFactory.php"
    - "tests/Architecture/ProductionArtifactsTest.php"
    - "tests/Architecture/MoneyConversionBoundaryTest.php"
    - "tests/Architecture/UnitConversionBoundaryTest.php"
    - "src/BusinessRecord/Infrastructure/Persistence/DoctrineRecordSecretRotation.php"
    - "src/BusinessRecord/Infrastructure/Security/ConfiguredSecretKeyRings.php"
    - "src/BusinessRecord/Domain/SecretKeyPurpose.php"
    - "src/BusinessRecord/Domain/RecordValueGuard.php"
    - "src/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipher.php"
    - "src/Identity/Application/StepUp/StepUpSecretCipher.php"
    - "src/Identity/Application/StepUp/TotpStepUpProvider.php"
    - "src/BusinessSurface/Application/MutationPlanCipher.php"
    - "src/BusinessSurface/Application/BusinessMutationPlanService.php"
    - "src/BusinessSurface/Infrastructure/Security/KeyRingMutationPlanCipher.php"
    - "tests/Unit/BusinessSurface/Application/BusinessMutationPlanServiceTest.php"
    - "tests/Deployment/record-key-rotation-strand.php"
    - "tests/Unit/Identity/Application/StepUp/TotpStepUpProviderTest.php"
    - "tests/Unit/BusinessRecord/Application/PostingPeriodLockTest.php"
    - "tests/Unit/BusinessRecord/Application/RecordRuleValidatorTest.php"
    - "tests/Unit/BusinessRecord/Application/BusinessRecordRelationshipCoordinatorTest.php"
    - "tests/Unit/BusinessRecord/Application/AggregateInvariantValidationTest.php"
    - "tests/Unit/BusinessRecord/Infrastructure/SecretKeyLifecycleTest.php"
    - "tests/Unit/BusinessRecord/Domain/RecordIntegrityTest.php"
    - "tests/Unit/BusinessRecord/Domain/ExactValueCodecTest.php"
    - "tests/Unit/Identity/Infrastructure/StepUp/SodiumStepUpSecretCipherTest.php"
    - "tests/Support/BusinessRuntimeBackupAcceptance.php"
    - "tests/Support/RestoreSecurityAcceptance.php"
  files_to_remove:
    - "src/BusinessRecord/Domain/EncryptedEnvelope.php"
    - "src/BusinessRecord/Domain/SecretKeyMaterial.php"
    - "src/BusinessRecord/Domain/SecretKeyRing.php"
    - "src/BusinessRecord/Domain/SecretKeyUnavailable.php"
    - "src/BusinessRecord/Application/SecretCipher.php"
    - "src/BusinessRecord/Application/SecretKeyProvider.php"
    - "src/BusinessRecord/Infrastructure/Security/SodiumSecretCipher.php"
    - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretCipher.php"
    - "src/BusinessRecord/Infrastructure/Security/KeyRingSecretKeyProvider.php"
  tests_to_remove:
    - "tests/Unit/BusinessRecord/Domain/EncryptedEnvelopeTest.php"
    - "tests/Unit/BusinessRecord/Infrastructure/SodiumSecretCipherTest.php"
  tests_to_retain_or_add:
    - "Configured key-ring legacy derivation, dedicated-key rotation, restore, audit and CLI integration"
    - "Host SecretAssociatedData scope binding and SecretKeyPurpose frozen derivation-label behavior"
  di_or_provisioning_changes:
    - "Bind canonical KeyProvider to records ring; compose record EnvelopeCipher with explicit factory."
    - "Keep mutation-plan provider/cipher separate, using mutationPlans ring; never alias both purposes together."
    - "Single-key constructor now accepts KeyMaterial rather than keyId/raw bytes."
  capability_index_changes:
    - "Regenerate App capability index from verified package manifests."
  changelog_and_evidence_changes:
    - "Complete migration/change-set 003, a release attestation and integration train; cite allocated NRM, no roadmap claim."
  verification_commands:
    - "composer install --no-interaction --prefer-dist"
    - "composer check"
    - "Run App architecture, secret rotation, restore, persistence, authorization and relevant database gates."
concurrency:
  likely_conflict_files:
    - "composer.json"
    - "composer.lock"
    - "src/Kernel/ContainerFactory.php"
  related_migrations: []
  ownership_conflicts: []
  integration_train: null
  resolution_rule: semantic-preservation
governance:
  roadmap_source_sha256: a202155ef1a65f5ab293d4f8397ebf4ac430db7f1e877c776bbe7851e6fe18d8
  roadmap_refs: []
  non_roadmap_refs: []
  completion_claim: false
decisions:
  - "Initial draft merged unfinished: this successor completes Phase 1 without adopting App or claiming a release."
  - "Retain App purpose enum, HKDF labels and field-coordinate wrapper; generic package values do not replace host policy."
  - "Preserve stored algorithm/base64 shape; add typed errors, serialization refusal and bounded parser safeguards."
  - "PSR-11 dependency is limited to explicit container integration; no domain service receives the container."
blockers: []
---

## Migration/implementation summary

Nine portable App types move to Kumwe\Secret. The package adds generic AssociatedData, KeyPurpose and
KeyIdentifier values, typed refusals, explicit DI and complete release verification. It does not move custody,
purpose selection, field-coordinate policy or persisted record orchestration. App remains unchanged in this PR.

## Public API and responsibility

The reflection-checked public API is in docs/public-api.md; machine-readable manifests and CHARTER.md define
every exported type, construction/lifetime and ownership. docs/architecture.md records all clean breaks.

## Capability reuse/semantic input review

App's Composer graph and canonical extraction catalog were inspected. Existing conversion, producer and SDK
packages own unrelated capabilities; no Kumwe dependency is selected. Sodium provides the only cipher primitive.
PSR-11 is used only by container integration. No native engine dependency or fallback is introduced.

## Consumer inventory

docs/extraction-inventory.json records exact source hashes and all matching App consumers. Nine old-to-new
symbol mappings above must be applied to code, tests, PHPDoc and configuration. Other Sodium-based identity,
Studio or trust protocols were inspected by responsibility and remain separate host protocol implementations.

## Test ownership

Remove EncryptedEnvelopeTest and SodiumSecretCipherTest after package adoption. SecretKeyLifecycleTest is
split: remove testKeyMaterialIsRedactedFromDebugOutput and testARingRefusesToHoldOneIdentifierTwice, already
owned by package tests. Keep configured-key, legacy derivation, per-purpose separation and rotation scenarios
as host integration, and remove vendor-class CoversClass annotations. Retain all database, restore, audit and
CLI tests. Add explicit host-coordinate binding coverage when splitting the cross-record replay test.

## Next-task execution notes

Do not delete SecretKeyPurpose or change any derivation label. ConfiguredSecretKeyRings continues deriving the
legacy application-secret-v1 key with its exact historical HMAC and dedicated keys with their existing HKDF.
Change its returned value types/imports to KeyMaterial/KeyRing. SecretAssociatedData delegates to AssociatedData
with its original domain marker and argument order. KeyRingMutationPlanCipher retains host token policy and
uses the package provider/cipher. ContainerFactory must keep record and mutation-plan bindings distinct.

## Drift check

At Phase 2, compare the recorded baseline to current App on every mapped source and affected test path; inspect
source hashes in extraction-inventory.json. Portable drift requires a separate package successor and release
verification; unrelated App changes are not blockers. Never replace current host behavior with an older copy.

## Validation recipe and observed local results

Use PHP 8.5 with Sodium; composer install then composer check. The full lane runs reflected API/documentation,
manifest/handoff, syntax, strict PHPStan, coding standards, behavioral/property/corpus/security tests, release
parser and a fresh archive dependency consumer before separately installing optional Laminas host integration.
Observed final-head results and immutable tested identities are reported in the PR and external CI record.
This handoff does not claim a release or its own final SHA/digest.

## Enforced package test ownership

Portable behavior, boundary and conformance evidence is maintained in `tests/ownership.json`,
validated against the public API and actual test-runner discovery by `composer test:ownership`.
See `docs/test-ownership.md` for the future-change rule and the precise host boundary.
This follow-up changes package tests/tooling only; it does not authorize early App test deletion.
