# Kumwe Secret Envelope

[![Packagist version][version-badge]][package]
[![CI][ci-badge]][ci]
[![PHP requirement][php-badge]](composer.json)
[![License][license-badge]](LICENSE)

[version-badge]: https://img.shields.io/packagist/v/kumwe/secret-envelope
[package]: https://packagist.org/packages/kumwe/secret-envelope
[ci-badge]: https://github.com/kumwe/secret-envelope/actions/workflows/ci.yml/badge.svg?branch=main
[ci]: https://github.com/kumwe/secret-envelope/actions/workflows/ci.yml?query=branch%3Amain
[php-badge]: https://img.shields.io/packagist/dependency-v/kumwe/secret-envelope/php
[license-badge]: https://img.shields.io/packagist/l/kumwe/secret-envelope

Authenticated XChaCha20-Poly1305 envelopes with explicit key identity and rotation reads. Canonical namespace:
`Kumwe\Secret`. Requires PHP 8.5 and ext-sodium. Apache-2.0.

## Installation and usage

Install the published release with an exact pre-1.0 pin:

```bash
composer require kumwe/secret-envelope:0.1.1
```

Review [release verification](docs/releasing.md) and key/ciphertext compatibility before upgrading.

```php
use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Value\AssociatedData;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;

// Demonstration key only: durable use requires host-managed persistent custody.
$key = new KeyMaterial('example-v1', sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
$cipher = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing($key)));
$binding = AssociatedData::for('example-record-v1', 'site-a', 'record-42', 'private-field');
$envelope = $cipher->encrypt('private value', $binding);
$plaintext = $cipher->decrypt($envelope, $binding);
```

Store only `$envelope->toStorage()`. Rebuild it with `EncryptedEnvelope::fromStorage()` and supply the same
binding; never log plaintext or key bytes. The runnable [examples](examples/README.md) verify this flow and
retired-key reads. Host custody, authorization, persistence, rotation and audit remain outside the package.

## Core composition

For Laminas/Mezzio, explicitly register `Kumwe\Secret\ConfigProvider::class` in the host's
`Laminas\ConfigAggregator\ConfigAggregator` provider list and bind `KeyProvider::class` to the host's provider.
See [integration](docs/integration.md) for the runnable ServiceManager example and multiple-purpose wiring.

| Service | Factory or construction | Lifetime |
|---|---|---|
| `Cipher\KeyRingEnvelopeCipher` | `Container\KeyRingEnvelopeCipherFactory` | Shared |
| `Contract\EnvelopeCipher` | Alias to ring cipher | Same shared instance |
| `Contract\KeyProvider` | Explicit host binding | Stable within request/job |
| Values, single-key cipher, ring provider | Direct construction | Caller owned |

There are no package configuration keys or implicit key defaults. The provider reads no ambient state and
constructs nothing; missing or wrong host bindings fail immediately. Automatic registration is not advertised.

Encryption uses a fresh 24-byte random nonce; plaintext is bounded to 1,000,000 bytes and associated data to
4,096 bytes. Storage ciphertext is bounded to 1,048,576 bytes. Decryption selects exactly one named key.
Authentication failure, unavailable keys and malformed envelopes are distinct typed refusals implementing
`Exception\SecretException`; errors never quote plaintext or key bytes. See [security](docs/security.md).

All values are immutable. Metadata serialization is deterministic; ciphertext is intentionally randomized.
The package starts no transaction and provides no interprocess key synchronization. A shared provider must
remain stable for each request or job; retirement and recovery ordering are host responsibilities.

The complete [public API](docs/public-api.md), [architecture](docs/architecture.md), machine-readable manifests
under `resources/`, and [release evidence](docs/release-record.md) define the Core contract and
compatibility requirements. Public contracts are the extension points; a missing portable capability belongs upstream.

## Development

```bash
composer install --no-interaction --prefer-dist
composer check
```

`composer check` includes syntax, docs, architecture, API/manifests/release contract, coding standards, strict static
analysis, behavioral/property/corpus tests, examples, security audit, archive validation and isolated no-dev
authoritative Composer consumption. Development also needs zip, mbstring and XML extensions for its tools.
[Releasing](docs/releasing.md) documents compatibility, release automation, Packagist and rollback.
