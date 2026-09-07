# Public API

Generated from reviewed source PHPDoc and reflection; `composer api:docs` rejects documentation drift.

All values are immutable; construction validates input. No method starts a transaction, reads a clock,
logs secrets or performs persistence. Encryption draws randomness; provider implementations may perform
host-governed I/O. Shared services require stable key providers per request/job; no process synchronization
or PHP-string zeroization is promised. See architecture, integration and security for complete ownership.

## `Kumwe\Secret\Cipher\KeyRingEnvelopeCipher`

`EnvelopeCipher` that seals under the provider's active key and opens under whichever key an envelope names.

`SodiumEnvelopeCipher` holds exactly one key, which is correct for the primitive and wrong for a system that
has to keep running while its keys change. This wraps it: encryption asks the provider for the active key,
decryption asks for the key the stored envelope names, and each call delegates to a single-key cipher bound to
that one key. Rotation therefore never means trying keys until one works, exactly one key is ever attempted
and it is chosen by identifier, and an envelope whose key the deployment no longer holds is reported as
unavailable rather than as a failed authentication.

The algorithm is pinned twice over: `EncryptedEnvelope` refuses to construct anything but the supported
construction, and this class re-checks the field it was handed, so a stored row edited to name a weaker
construction is refused before a key is touched rather than after. A provider that answers a request for one
identifier with a key of another is refused the same way, before its bytes are used.

@since  0.1.0

### `__construct()`

```php
__construct(
    Kumwe\Secret\Contract\KeyProvider $keys,
)
```

Bind the cipher to the provider that answers for key material.

@param  KeyProvider  $keys  Source of the active key and of retired keys by identifier.

@since  0.1.0

### `encrypt()`

```php
encrypt(
    string $plaintext,
    string $associatedData,
): Kumwe\Secret\Value\EncryptedEnvelope
```

Seal a value under the provider's active key.

@param   string  $plaintext       Value to protect, at most `SodiumEnvelopeCipher::MAXIMUM_PLAINTEXT_BYTES`.
@param   string  $associatedData  Binding authenticated alongside the ciphertext but not stored, at most
         `SodiumEnvelopeCipher::MAXIMUM_ASSOCIATED_DATA_BYTES`; the same string must be supplied to decrypt.

@return  EncryptedEnvelope  Ciphertext, its fresh nonce, and the active key identifier.

@throws  InvalidInput      When the plaintext or the associated data exceeds its bound.
@throws  KeyUnavailable    When the provider cannot produce its own active key, which stops the write rather
         than degrading it.
@throws  EncryptionFailed  When the random source or libsodium refuses the encryption.

@since   0.1.0

### `decrypt()`

```php
decrypt(
    Kumwe\Secret\Value\EncryptedEnvelope $envelope,
    string $associatedData,
): string
```

Open an envelope under the one key its identifier names.

@param   EncryptedEnvelope  $envelope        Stored ciphertext with its nonce and key identifier.
@param   string             $associatedData  The binding used at encryption time; any difference fails
         authentication.

@return  string  The original plaintext, byte for byte.

@throws  InvalidEnvelope       When the envelope names an unsupported construction.
@throws  InvalidInput          When the associated data exceeds its bound.
@throws  KeyUnavailable        When the envelope names a key the provider does not hold, or the provider
         answers with a key of another identifier; both are distinct from a failed authentication.
@throws  AuthenticationFailed  When libsodium refuses the input or the ciphertext fails authentication.

@since   0.1.0

## `Kumwe\Secret\Cipher\SodiumEnvelopeCipher`

`EnvelopeCipher` backed by libsodium's XChaCha20-Poly1305 AEAD, holding exactly one key.

The instance is bound to a single key and the identifier that names it, which it stamps into every envelope it
writes and insists on again before decrypting: an envelope written under a different key is reported as
unavailable rather than attempted, so rotation means wiring a `KeyRingEnvelopeCipher` and never means trying
keys until one works. Each encryption draws a fresh random nonce from the platform's random source, and the
caller's associated data is authenticated but not stored, so a ciphertext copied elsewhere no longer opens.
Only vetted libsodium primitives are used; nothing here is a custom construction.

@since  0.1.0

### `MAXIMUM_PLAINTEXT_BYTES`

`1000000`

Largest plaintext accepted, which keeps the resulting ciphertext inside the envelope's own bound.

@var    int
@since  0.1.0

### `MAXIMUM_ASSOCIATED_DATA_BYTES`

`4096`

Largest associated data accepted on either side.

@var    int
@since  0.1.0

### `__construct()`

```php
__construct(
    Kumwe\Secret\Value\KeyMaterial $key,
)
```

Bind the cipher to one key; the value has already proved its identifier and size.

@param  KeyMaterial  $key  Key recorded in every envelope and matched on decryption.

@since  0.1.0

### `keyId()`

```php
keyId(
): string
```

Name the key this instance seals under and opens with.

@return  string  Identifier only, safe to log.

@since   0.1.0

### `encrypt()`

```php
encrypt(
    string $plaintext,
    string $associatedData,
): Kumwe\Secret\Value\EncryptedEnvelope
```

Seal a value under this instance's key with a nonce drawn fresh for this call.

Both inputs are bounded first, so an oversized value is refused before any key material is touched, and
the plaintext bound is what keeps the resulting ciphertext inside the envelope's own limit.

@param   string  $plaintext       Value to protect, at most `MAXIMUM_PLAINTEXT_BYTES`.
@param   string  $associatedData  Binding authenticated alongside the ciphertext but not stored, at most
         `MAXIMUM_ASSOCIATED_DATA_BYTES`; the same string must be supplied again to decrypt.

@return  EncryptedEnvelope  Ciphertext, the nonce it was sealed with, and this instance's key identifier.

@throws  InvalidInput      When the plaintext or the associated data exceeds its bound.
@throws  EncryptionFailed  When the random source cannot supply a nonce or libsodium refuses the encryption.

@since   0.1.0

### `decrypt()`

```php
decrypt(
    Kumwe\Secret\Value\EncryptedEnvelope $envelope,
    string $associatedData,
): string
```

Open an envelope this instance's key sealed and hand back the value.

The key identifier is compared first, in constant time, so an envelope from a rotated or foreign key fails
as an unavailable key instead of as a decryption error. Authentication then covers the ciphertext, the
nonce and the associated data together, and every way they can disagree is one indistinguishable refusal.

@param   EncryptedEnvelope  $envelope        Stored ciphertext with its nonce and the identifier of the key
         that sealed it.
@param   string             $associatedData  The binding used at encryption time; any difference fails
         authentication.

@return  string  The original plaintext, byte for byte.

@throws  InvalidInput          When the associated data exceeds its bound.
@throws  KeyUnavailable        When the envelope names another key than the one this instance holds.
@throws  AuthenticationFailed  When libsodium refuses the input or the ciphertext fails authentication.

@since   0.1.0

## `Kumwe\Secret\ConfigProvider`

Deterministic Laminas/Mezzio configuration for the one injected runtime service this package exports.

`KeyRingEnvelopeCipher` is the only service with an injected collaborator, so it is the only thing registered:
one explicit factory, one alias binding the `EnvelopeCipher` port to it, and a shared lifetime. Values,
contracts, exceptions, the single-key cipher and the in-process key provider stay out of the container; they
are constructed directly by whoever holds the key material, which is the host. The provider declares no
configuration keys, reads no environment and constructs nothing. The host binds `KeyProvider::class` to its
own custody adapter and registers this provider explicitly with its configuration aggregator.

@since  0.1.0

### `__invoke()`

```php
__invoke(
): array
```

Return the package configuration in the shape a Mezzio configuration aggregator merges.

@return  array{
             dependencies: array{
                 factories: array<class-string, class-string<KeyRingEnvelopeCipherFactory>>,
                 aliases: array<class-string, class-string>,
                 shared: array<class-string, bool>
             }
         }  Only this package's own services; identical on every call.

@since   0.1.0

### `getDependencies()`

```php
getDependencies(
): array
```

Return the service-manager configuration alone, for a host that assembles its container by hand.

@return  array{
             factories: array<class-string, class-string<KeyRingEnvelopeCipherFactory>>,
             aliases: array<class-string, class-string>,
             shared: array<class-string, bool>
         }  The factory, the alias and the lifetime; identical on every call.

@since   0.1.0

## `Kumwe\Secret\Container\KeyRingEnvelopeCipherFactory`

Constructs the shared `KeyRingEnvelopeCipher` from the key provider the host bound.

The factory asks the container for exactly one collaborator, the canonical `KeyProvider::class` identifier,
and refuses anything that does not implement the port before wiring it, so a wrong binding fails at
construction instead of at the first secret write. It reads no configuration, because the package declares no
configuration keys, and it never passes the container on. The service is shared: the cipher is immutable and
holds only the provider, which the host already keeps for the life of the process.

@since  0.1.0

### `__invoke()`

```php
__invoke(
    Psr\Container\ContainerInterface $container,
): Kumwe\Secret\Cipher\KeyRingEnvelopeCipher
```

Build the cipher over the host's key provider.

@param   ContainerInterface  $container  Container in which the host bound `KeyProvider::class`.

@return  KeyRingEnvelopeCipher  The shared ring cipher.

@throws  ServiceBindingRefused  When `KeyProvider::class` resolves to something that is not a key provider.
@throws  \Psr\Container\NotFoundExceptionInterface  When the host bound no key provider at all.

@since   0.1.0

## `Kumwe\Secret\Contract\EnvelopeCipher`

Port through which values are sealed into envelopes and opened again.

A host reaches for this wherever a value must never reach storage in the clear, so no plaintext ever reaches a
column and the persistence layer never sees a key. Implementations owe authenticated encryption: the
associated data is authenticated but not stored, which is what stops an envelope from being replayed into a
different place. Keeping this a port is what lets the key source, libsodium in one process or a managed KMS in
another, change without touching the code that stores envelopes.

@since  0.1.0

### `encrypt()`

```php
encrypt(
    string $plaintext,
    string $associatedData,
): Kumwe\Secret\Value\EncryptedEnvelope
```

Seal a value into an envelope bound to the caller's associated data.

An implementation must draw a fresh nonce per call, so sealing the same plaintext twice yields different
envelopes and stored ciphertext never reveals that two places hold the same value.

@param   string  $plaintext       Value to protect, at most the implementation's documented bound; only the
         returned envelope is persisted.
@param   string  $associatedData  Binding such as `AssociatedData::for()` composes, authenticated but not
         stored, at most the implementation's documented bound.

@return  EncryptedEnvelope  Ciphertext, nonce and key identifier, ready to be written to storage.

@throws  \Kumwe\Secret\Exception\InvalidInput  When the plaintext or the associated data exceeds its bound.
@throws  \Kumwe\Secret\Exception\KeyUnavailable  When the implementation cannot produce the key new
         envelopes are sealed under, which must stop the write rather than degrade it.
@throws  \Kumwe\Secret\Exception\EncryptionFailed  When the random source or the AEAD primitive refuses.

@since   0.1.0

### `decrypt()`

```php
decrypt(
    Kumwe\Secret\Value\EncryptedEnvelope $envelope,
    string $associatedData,
): string
```

Open an envelope and return the value it protects.

Decryption fails closed. An implementation must refuse rather than return a value when the envelope names
a key it does not hold, when the ciphertext or nonce has been altered, or when the associated data differs
by so much as one byte from the binding used to seal it, which is how an envelope copied between places
is caught.

@param   EncryptedEnvelope  $envelope        Envelope as read back from storage.
@param   string             $associatedData  The same binding that was supplied to `encrypt()`.

@return  string  The original plaintext, byte for byte.

@throws  \Kumwe\Secret\Exception\InvalidEnvelope  When the envelope names an unsupported construction.
@throws  \Kumwe\Secret\Exception\InvalidInput  When the associated data exceeds its bound.
@throws  \Kumwe\Secret\Exception\KeyUnavailable  When the envelope names a key the implementation does not
         hold, which is a distinct condition from a failed authentication.
@throws  \Kumwe\Secret\Exception\AuthenticationFailed  When the ciphertext, nonce, key bytes or associated
         data do not authenticate together.

@since   0.1.0

## `Kumwe\Secret\Contract\KeyProvider`

Port through which a cipher acquires key material, separate from the cipher itself.

`EnvelopeCipher` says how bytes are sealed; this says where the key comes from. Splitting them is what lets a
host keep an in-process ring built from its configuration, or put a managed KMS or an HSM behind the same two
questions, which key should new writes use and can you produce the key this envelope names, without the code
that stores envelopes knowing which answer it is talking to.

**Adapter contract.** An implementation owes the following:

- *Identifier namespace.* `activeKeyId()` returns a stable, versioned name in the `KeyIdentifier` grammar. It
  is written into every envelope and is the only thing storage records about the key, so it must never be
  reused for different key material: a new key is a new identifier, always.
- *Stability within a process.* `activeKeyId()` and `activeKey()` must agree and must not change during one
  request or one job. A provider that rotates underneath a running batch would leave envelopes stamped with an
  identifier the bytes do not match.
- *Fail closed.* `keyFor()` raises `KeyUnavailable` for an identifier it cannot produce, including a revoked
  one, and never substitutes another key. Returning the wrong key would turn a recoverable "restore the key"
  into a silent authentication failure; the ring cipher additionally refuses a key whose identifier differs
  from the one it asked for.
- *Disclosure.* No implementation may log, print or attach key material to an exception, a metric or a trace.
  `KeyMaterial` redacts itself; an adapter must not undo that by logging the bytes it received before wrapping
  them.
- *Latency and caching.* Every write and every re-encryption calls `activeKey()`, so a remote provider must
  cache within the process and must bound its network wait; a provider that blocks makes writes block. A cache
  entry is dropped on revocation signalling, which for this port means the next `keyFor()` raising
  `KeyUnavailable`.
- *Audit.* Key use is not audited here; a host's own trail records the mutation, and its rotation pass records
  what it re-encrypted. An external provider is expected to keep its own access log.

@since  0.1.0

### `activeKeyId()`

```php
activeKeyId(
): string
```

Name the key every new envelope must be sealed under.

@return  string  Identifier of the active key, in the `KeyIdentifier` grammar.

@since   0.1.0

### `activeKey()`

```php
activeKey(
): Kumwe\Secret\Value\KeyMaterial
```

Produce the key every new envelope must be sealed with.

@return  KeyMaterial  Active key and its identifier, which must equal `activeKeyId()`.

@throws  \Kumwe\Secret\Exception\KeyUnavailable  When the provider cannot produce its own active key, which
         is a deployment fault rather than a data fault and must stop writes rather than degrade them.

@since   0.1.0

### `keyFor()`

```php
keyFor(
    string $keyId,
): Kumwe\Secret\Value\KeyMaterial
```

Produce the key one stored envelope names.

@param   string  $keyId  Identifier read from the envelope, which may name a retired key.

@return  KeyMaterial  The key that identifier names.

@throws  \Kumwe\Secret\Exception\KeyUnavailable  When the identifier names a key this provider does not
         hold, has retired, or has had revoked.

@since   0.1.0

### `knownKeyIds()`

```php
knownKeyIds(
): array
```

Name every key this provider can currently open an envelope with.

Operators read this before and after a rotation to confirm that a retired key is still loaded, and that it
has been dropped once nothing references it. A provider that cannot enumerate its keys, as some KMS
deployments deliberately cannot, returns just the active identifier, which is honest: it says only that the
active key is present, not that no others are.

@return  non-empty-list<string>  Identifiers, active first; names only, never material.

@since   0.1.0

## `Kumwe\Secret\Exception\AuthenticationFailed`

Signals that an envelope did not authenticate under the key it names and the binding it was given.

Any single altered byte of the ciphertext or nonce, any difference in the associated data, a truncated
ciphertext, or a key whose bytes differ from the ones that sealed the envelope ends here. The package never
says which of those it was: distinguishing them would hand an attacker an oracle, so every authentication
failure is one indistinguishable refusal. A key the process does not hold at all is a different answer,
`KeyUnavailable`, because that is recoverable and this is not.

@since  0.1.0

## `Kumwe\Secret\Exception\EncryptionFailed`

Signals that libsodium or the random source refused to seal a value.

This is the rare, environmental failure: the nonce could not be drawn from the platform's random source or the
AEAD primitive rejected its inputs. It stops the write. The underlying engine exception is deliberately not
chained, because an engine frame carries the key and the plaintext as call arguments, and an exception that
reaches an error tracker must not carry either.

@since  0.1.0

## `Kumwe\Secret\Exception\InvalidEnvelope`

Refuses an envelope whose parts are malformed before any key is touched.

Raised by `EncryptedEnvelope` when a ciphertext is empty, shorter than the authentication tag or oversized,
a nonce has the wrong length, a key identifier is malformed, a stored row is incomplete or not valid base64,
or the algorithm is anything but the one supported construction. A downgraded or corrupted stored row is
therefore reported as a damaged envelope, which is a data-integrity finding, rather than surfacing later as
an authentication failure that looks like tampering.

@since  0.1.0

## `Kumwe\Secret\Exception\InvalidInput`

Refuses a caller-supplied value that would push a cipher or a binding outside its safe bounds.

Raised when a plaintext or its associated data exceeds the documented size limit, or when an associated-data
coordinate would make two different bindings produce the same bytes. Both are checked before any key material
is read, so an oversized or ambiguous input never reaches libsodium.

@since  0.1.0

## `Kumwe\Secret\Exception\InvalidKeyMaterial`

Refuses key material, a key identifier, a key ring or a key purpose that cannot be used safely.

Raised at construction, which is the only moment configuration is admitted: a key of the wrong size, an
identifier outside the accepted grammar, a ring that would hold one identifier twice or more retired keys
than it bounds, or a purpose with an empty or malformed derivation label. This is a deployment fault, never
a data fault, and it stops a process from starting rather than letting it seal under an unusable key.
The message names the rule that was broken and never the bytes that broke it.

@since  0.1.0

## `Kumwe\Secret\Exception\KeyUnavailable`

Signals that the key an envelope names is not held by this process.

This is deliberately a different answer from a failed authentication. An envelope whose key the provider does
not hold has not been tampered with: it was sealed under a key that has been retired without being kept,
revoked by an external provider, derived for another purpose, or that belongs to another installation
entirely. Telling the two apart is what lets an operator distinguish "restore the key" from "this ciphertext
is not what it claims". A purpose mismatch surfaces here too, because each purpose holds its own ring and its
own identifiers, so an envelope presented to the wrong purpose names a key that ring does not hold.

The message names the requested identifier only, never key material, plaintext, or the identifiers of the
keys that are held: an unavailable-key error reaching a log must not become a map of the ring.

@since  0.1.0

### `__construct()`

```php
__construct(
    string $keyId,
)
```

Report that one named key is not available for use.

@param  string  $keyId  Identifier the envelope or caller named. It is a key name, never key bytes, and is
        included so an operator can tell which key to restore; a malformed identifier is reported without
        being echoed.

@since  0.1.0

### `keyId()`

```php
keyId(
): string
```

Name the key that was asked for, so a host can route the refusal without parsing the message.

@return  string  The requested identifier, or `unnamed` when it was malformed.

@since   0.1.0

## `Kumwe\Secret\Exception\SecretDisclosureRefused`

Refuses an operation that would copy key material into a form the package cannot redact.

`serialize()` on a `KeyMaterial` would write the raw key into a string that then travels through caches,
queues, sessions and logs with no redaction hook of its own. The value refuses rather than complying, and
this is the refusal: a programming error, reported as a `LogicException`, that must be fixed at the call site.

@since  0.1.0

## `Kumwe\Secret\Exception\SecretException`

Marker every refusal raised by this package carries, so a host can catch the whole family at one boundary.

Each concrete exception also extends the SPL type its condition has always mapped to: `InvalidArgumentException`
for a refused input, envelope or configuration, `RuntimeException` for a failure while sealing or opening, and
`LogicException` for a disclosure the package refuses to perform. A caller that already catches those keeps
working. No message anywhere in the family quotes plaintext or key material.

@since  0.1.0

## `Kumwe\Secret\Exception\ServiceBindingRefused`

Refuses to construct a package service because the host bound a collaborator of the wrong type.

The package factories resolve their collaborators by canonical service identifier and check the result
before wiring it, so a container that binds `KeyProvider::class` to something that is not a key provider
fails at construction rather than at the first secret write. It implements the PSR-11 container exception
marker so a Laminas ServiceManager surfaces it unchanged instead of wrapping it.

@since  0.1.0

### `forService()`

```php
static forService(
    string $serviceId,
    string $expected,
    string $actual,
): Kumwe\Secret\Exception\ServiceBindingRefused
```

Describe a binding that resolved to the wrong type.

@param   string  $serviceId  Canonical service identifier the factory asked the container for.
@param   string  $expected   Contract the bound service had to implement.
@param   string  $actual     Debug type the container returned instead.

@return  self  The refusal, naming identifiers and types only.

@since   0.1.0

## `Kumwe\Secret\Provider\KeyRingKeyProvider`

The production-capable default `KeyProvider`: an in-process ring the host builds from its own custody.

Key material arrives from the deployment, an environment variable, a mounted file, a secret store, is derived
by the host into per-purpose rings, and is held in memory for the life of the process. That satisfies every
clause of the adapter contract without a network round trip: the active identifier cannot change mid-request,
resolution is by identifier, and an identifier the ring does not hold fails closed.

It is the reference implementation an external adapter is measured against rather than a placeholder. A KMS or
HSM adapter replaces this one class and nothing else; what it must additionally solve, caching, bounded
latency, revocation, is what a ring in memory gets for free.

@since  0.1.0

### `__construct()`

```php
__construct(
    Kumwe\Secret\Value\KeyRing $ring,
)
```

Bind the provider to the ring it answers from.

@param  KeyRing  $ring  Active key plus the retired keys this deployment still holds.

@since  0.1.0

### `activeKeyId()`

```php
activeKeyId(
): string
```

Name the ring's active key.

@return  string  Identifier stamped into every envelope sealed from now on.

@since   0.1.0

### `activeKey()`

```php
activeKey(
): Kumwe\Secret\Value\KeyMaterial
```

Produce the ring's active key.

@return  KeyMaterial  The active key; a constructed ring always has one, so this never fails.

@since   0.1.0

### `keyFor()`

```php
keyFor(
    string $keyId,
): Kumwe\Secret\Value\KeyMaterial
```

Resolve the key an envelope names from the ring.

@param   string  $keyId  Identifier read from the stored envelope.

@return  KeyMaterial  The key that identifier names.

@throws  KeyUnavailable  When the ring holds no such key.

@since   0.1.0

### `knownKeyIds()`

```php
knownKeyIds(
): array
```

Name every key the ring can open an envelope with.

@return  non-empty-list<string>  Active identifier first, then the retired ones in string order.

@since   0.1.0

## `Kumwe\Secret\Value\AssociatedData`

Composes the associated data that binds an envelope to the one place it belongs.

The AEAD construction authenticates associated data without storing it, so a ciphertext lifted out of one
place and pasted into another no longer authenticates and cannot be opened: the database alone is not enough
to move a secret around. This type owns the composition rule only. A versioned domain marker opens the
binding, so a future change can be introduced without silently accepting envelopes written under the old one,
and the coordinates follow, one per line. Which coordinates identify a place is the host's policy: a record
host binds a field to its site, definition, record and field; a token host binds a token to its audience.

The separator is refused inside a coordinate, because otherwise two different coordinate lists could produce
the same bytes and two different places would share one binding.

@since  0.1.0

### `SEPARATOR`

`'
'`

Byte that separates the marker and the coordinates.

@var    string
@since  0.1.0

### `DOMAIN_PATTERN`

`'/^[A-Za-z0-9][A-Za-z0-9._:-]{0,126}$/D'`

Anchored grammar of a domain marker: the key-identifier grammar, which admits a versioned name.

@var    string
@since  0.1.0

### `for()`

```php
static for(
    string $domain,
    string ...$coordinates,
): string
```

Compose the binding for one place, opening with its versioned domain marker.

The same marker and coordinates must be supplied again at decryption time or authentication fails.

@param   string  $domain       Versioned domain marker, such as `business-record-secret-v1`, matching
         `DOMAIN_PATTERN`.
@param   string  $coordinates  Coordinates of the place, in the host's fixed order; none may contain the
         separator.

@return  string  Separator-joined binding, opening with the marker; opaque to callers and never stored.

@throws  InvalidInput  When the marker leaves its grammar or a coordinate contains the separator.

@since   0.1.0

## `Kumwe\Secret\Value\EncryptedEnvelope`

A sealed value together with everything needed to open it again, and nothing that would open it.

The envelope is the versioned storage format of the package: the raw AEAD output with its authentication tag,
the nonce it was sealed under, the identifier of the key that sealed it and the name of the construction.
Naming the key and the construction alongside the ciphertext is what makes rotation survivable, because a
decrypt against the wrong key is refused by identifier rather than mistaken for corrupt data, and it is what
stops a downgraded stored row from loading. The constructor is the single gate: an envelope rebuilt from a
stored row is validated exactly as one produced by a cipher. The value carries no plaintext and no key.

@since  0.1.0

### `ALGORITHM`

`'xchacha20poly1305-ietf'`

The only AEAD construction envelopes may be sealed with, in its portable spelling.

@var    string
@since  0.1.0

### `MINIMUM_CIPHERTEXT_BYTES`

`16`

Shortest ciphertext the construction can produce: the authentication tag of an empty plaintext.

@var    int
@since  0.1.0

### `MAXIMUM_CIPHERTEXT_BYTES`

`1048576`

Largest ciphertext an envelope admits, one mebibyte, which bounds what a stored row can make a reader load.

@var    int
@since  0.1.0

### `NONCE_BYTES`

`24`

Exact nonce length of the construction, 192 bits.

@var    int
@since  0.1.0

### `$ciphertext`

Type: `string`; readonly. The constructor documents its invariant.

### `$nonce`

Type: `string`; readonly. The constructor documents its invariant.

### `$keyId`

Type: `string`; readonly. The constructor documents its invariant.

### `$algorithm`

Type: `string`; readonly. The constructor documents its invariant.

### `__construct()`

```php
__construct(
    string $ciphertext,
    string $nonce,
    string $keyId,
    string $algorithm = self::ALGORITHM,
)
```

Assemble an envelope and prove each part is well formed before it can be stored or opened.

@param   string  $ciphertext  Raw sealed bytes including the authentication tag; at least the tag length
         and at most `MAXIMUM_CIPHERTEXT_BYTES`.
@param   string  $nonce       Raw nonce bytes, exactly `NONCE_BYTES` long.
@param   string  $keyId       Identifier of the key the ciphertext was sealed with, in the
         `KeyIdentifier` grammar, so a rotated key is recognised instead of silently mis-applied.
@param   string  $algorithm   Construction the ciphertext was produced with; anything other than
         `ALGORITHM` is refused, which is what stops a downgraded stored row from loading.

@throws  InvalidEnvelope  When the ciphertext is too short or oversized, the nonce is the wrong length, the
         key identifier is malformed, or the algorithm is unsupported. No message quotes the bytes.

@since   0.1.0

### `toStorage()`

```php
toStorage(
): array
```

Export the envelope in its deterministic, text-safe storage shape.

The two byte strings are base64-encoded because raw AEAD output is not valid UTF-8 and could not be
JSON-encoded; the identifier and the algorithm travel verbatim. The key order and encoding are fixed, so
the same envelope always produces the same row, which is what lets a host checksum or compare it.

@return  array{ciphertext: string, nonce: string, key_id: string, algorithm: string}  Byte strings
         base64-encoded, key identifier and algorithm verbatim, in this key order.

@since   0.1.0

### `fromStorage()`

```php
static fromStorage(
    array $storage,
): Kumwe\Secret\Value\EncryptedEnvelope
```

Rebuild an envelope from its storage row, decoding the two base64 byte strings strictly.

The row is treated as hostile: a missing or non-string member, padding or alphabet damage in a byte
string, and every constructor refusal are all reported here rather than surfacing later as a failed
decryption. Members the row carries beyond the four known ones are ignored.

@param   array<string, mixed>  $storage  Row as written by `toStorage()`.

@return  self  An envelope that has passed the same checks as a freshly sealed one.

@throws  InvalidEnvelope  When a member is missing or not a string, either byte string is not valid base64,
         or the decoded envelope fails construction.

@since   0.1.0

## `Kumwe\Secret\Value\KeyIdentifier`

The grammar and comparison rule every key identifier in the package obeys.

A key identifier is the only thing a stored envelope records about the key that sealed it, so it has to be
safe to write into a column, a log line and an error message, and stable enough to be compared years later.
The grammar is one alphanumeric character followed by up to 126 more of `A-Za-z0-9._:-`: no whitespace, no
control characters, no path or quote characters, and a bounded length. An identifier names a key; it must
never be reused for different key material, because rotation resolves keys by identifier and never by trial.

@since  0.1.0

### `PATTERN`

`'/^[A-Za-z0-9][A-Za-z0-9._:-]{0,126}$/D'`

Anchored expression an identifier must match in full.

@var    string
@since  0.1.0

### `MAXIMUM_LENGTH`

`127`

Longest identifier the grammar admits, in bytes.

@var    int
@since  0.1.0

### `isValid()`

```php
static isValid(
    string $keyId,
): bool
```

Decide whether a string is a well-formed key identifier.

@param   string  $keyId  Candidate identifier.

@return  bool  True when the whole string matches the grammar.

@since   0.1.0

### `equals()`

```php
static equals(
    string $known,
    string $presented,
): bool
```

Compare a presented identifier with a known one in constant time.

The active identifier is compared this way on every decryption, so the common path leaks no timing
signal about how much of the identifier an attacker guessed.

@param   string  $known      Identifier this process holds.
@param   string  $presented  Identifier read from an envelope or a caller.

@return  bool  True when both are byte-identical.

@since   0.1.0

## `Kumwe\Secret\Value\KeyMaterial`

One AEAD key together with the identifier envelopes record it under.

A key on its own cannot be rotated, because nothing sealed with it says so; pairing the bytes with a versioned
name is what lets a `KeyRing` hand back the right key for an envelope written years ago instead of trying keys
until one happens to authenticate. The bytes stay private and are reachable only through `material()`; the
constructor parameter is marked sensitive so a stack trace redacts it; `__debugInfo()` replaces it so a
`var_dump()` or `print_r()` cannot spill it; and `serialize()` is refused outright. `var_export()` and
reflection bypass every redaction PHP offers, so a host must never apply them to a key.

@since  0.1.0

### `KEY_BYTES`

`32`

Exact key length of the construction, 256 bits.

@var    int
@since  0.1.0

### `$keyId`

Type: `string`; readonly. The constructor documents its invariant.

### `__construct()`

```php
__construct(
    string $keyId,
    string $material,
)
```

Bind an identifier to its key and prove both halves are usable before anything is sealed.

@param   string  $keyId     Name of the key, stamped into every envelope it seals, in the `KeyIdentifier`
         grammar.
@param   string  $material  Raw XChaCha20-Poly1305 key bytes of exactly `KEY_BYTES`: derived material,
         never a passphrase or its hexadecimal spelling.

@throws  InvalidKeyMaterial  When the identifier does not match the grammar, or the key is not exactly the
         algorithm's key size. Neither message quotes the key.

@since   0.1.0

### `material()`

```php
material(
): string
```

Hand the raw key bytes to the one collaborator entitled to them.

Only a cipher calls this. Everything else works with the identifier, which is safe to log.

@return  string  The raw key bytes, exactly as supplied.

@since   0.1.0

### `__debugInfo()`

```php
__debugInfo(
): array
```

Present the key to a debugger with its bytes replaced.

`var_dump()` on an object graph is one of the ways key material escapes into a log or a bug report, and
this is the hook that stops it: the identifier is disclosed, the key never is.

@return  array{keyId: string, material: string}  The identifier, and a fixed redaction marker.

@since   0.1.0

### `__serialize()`

```php
__serialize(
): array
```

Refuse to serialize, because a serialized key has no redaction hook wherever it lands.

@return  array<never, never>  Never returns; declared so PHP accepts the magic method signature.

@throws  SecretDisclosureRefused  Always.

@since   0.1.0

## `Kumwe\Secret\Value\KeyPurpose`

One reason a host holds key material, with the frozen coordinates that keep it separate from every other.

Two very different things can be sealed with the same construction: durable stored fields that outlive every
process that wrote them, and short-lived tokens handed to a browser. Sharing one key makes their lifecycles one
lifecycle, so a purpose gives each its own derivation label and its own default key identifier: keys derived
for different purposes are unrelated bytes, and each purpose is served by its own `KeyRing`, so an envelope
from one purpose presented to another is refused as `KeyUnavailable` by identifier. Which purposes exist, and
how their keys are derived from configured secrets, is the host's key custody; this value only carries the
coordinates. A derivation label is frozen for the life of the envelopes sealed under it: changing it changes
every key derived from the same secret. A new derivation gets a new label and a new identifier instead.

@since  0.1.0

### `LABEL_PATTERN`

`'/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{0,254}$/D'`

Anchored grammar of a derivation label: printable, whitespace-free, bounded.

@var    string
@since  0.1.0

### `$name`

Type: `string`; readonly. The constructor documents its invariant.

### `$derivationLabel`

Type: `string`; readonly. The constructor documents its invariant.

### `$defaultKeyId`

Type: `string`; readonly. The constructor documents its invariant.

### `__construct()`

```php
__construct(
    string $name,
    string $derivationLabel,
    string $defaultKeyId,
)
```

Name a purpose and prove its coordinates are usable.

@param   string  $name             Short name of the purpose, in the `KeyIdentifier` grammar; it labels
         rings and diagnostics and never reaches an envelope.
@param   string  $derivationLabel  Info string the host derives this purpose's key material under; it must
         differ from every other purpose's label and never change once envelopes exist.
@param   string  $defaultKeyId     Identifier stamped into envelopes when a deployment configures no
         identifier of its own, in the `KeyIdentifier` grammar.

@throws  InvalidKeyMaterial  When the name or default identifier leaves the identifier grammar, or the label
         leaves `LABEL_PATTERN`.

@since   0.1.0

## `Kumwe\Secret\Value\KeyRing`

One active key plus every retired key still needed to open what it sealed.

A single key cannot be rotated: the moment it is replaced, every envelope written under it becomes unreadable,
so rotation and re-encryption would have to happen atomically, which they cannot. A ring breaks that deadlock.
New writes always use the active key; a read resolves whichever key the envelope names, which may be a key
retired several rotations ago. That is what makes re-encryption an ordinary background pass instead of an
outage.

Resolution is by identifier and never by trial: a key the ring does not hold raises `KeyUnavailable` rather
than being attempted, so an envelope from another installation or another purpose fails as a missing key
instead of as a corrupted ciphertext. Retiring a key means removing it from the ring, which is also how a
revocation is expressed: from this side, a revoked key and a key that was never configured are the same
condition, and both fail closed.

@since  0.1.0

### `MAXIMUM_RETIRED_KEYS`

`32`

Most retired keys a ring holds, which bounds what a misconfiguration can make a process load.

@var    int
@since  0.1.0

### `$active`

Type: `Kumwe\Secret\Value\KeyMaterial`; readonly. The constructor documents its invariant.

### `__construct()`

```php
__construct(
    Kumwe\Secret\Value\KeyMaterial $active,
    array $previous = array (
),
)
```

Assemble a ring from its active key and the retired keys still worth holding.

A retired key sharing the active identifier is refused rather than silently dropped: two different keys
under one name would make an envelope's identifier ambiguous, which is exactly the property the ring
exists to provide.

@param   KeyMaterial        $active    Key every new envelope is sealed under.
@param   list<KeyMaterial>  $previous  Retired keys, in any order, kept so envelopes written under them
         still open while re-encryption runs.

@throws  InvalidKeyMaterial  When a retired entry is not key material, repeats the active identifier or
         another retired identifier, or more than `MAXIMUM_RETIRED_KEYS` retired keys are supplied.

@since   0.1.0

### `keyFor()`

```php
keyFor(
    string $keyId,
): Kumwe\Secret\Value\KeyMaterial
```

Resolve the key an envelope names, whether it is the active one or a retired one.

The active identifier is compared in constant time, so the common path leaks no timing signal about the
identifier; a retired identifier is looked up by name.

@param   string  $keyId  Identifier read from the stored envelope.

@return  KeyMaterial  The key that identifier names.

@throws  KeyUnavailable  When the ring holds no key under that identifier, which covers a retired key that
         was dropped, a revoked key, a key of another purpose and a key from another installation alike.

@since   0.1.0

### `keyIds()`

```php
keyIds(
): array
```

Name every key this ring can open an envelope with, active first.

Operators use it to confirm that a retired key is still loaded before they start a rotation, and that it
is gone once the rotation has finished. Identifiers are names, not material.

@return  non-empty-list<string>  The active identifier followed by the retired ones, in ascending string
         order.

@since   0.1.0

