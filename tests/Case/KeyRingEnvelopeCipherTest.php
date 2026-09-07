<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Exception\AuthenticationFailed;
use Kumwe\Secret\Exception\InvalidEnvelope;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\Support\ScriptedKeyProvider;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;
use RuntimeException;

/**
 * Proves the ring cipher rotates without stranding anything it has already sealed, and fails closed on every
 * provider misbehaviour.
 *
 * @since  0.1.0
 */
final class KeyRingEnvelopeCipherTest extends TestCase
{
    /**
     * Prove an envelope sealed under a key that is later retired still opens once the key is retired.
     *
     * This is rotation-read compatibility in one assertion: the same binding, the same stored row, a ring whose
     * active key is now something else entirely, and the value still comes back.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEnvelopesSealedUnderARetiredKeyStillOpenAfterRotation(): void
    {
        $before = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1'))));
        $stored = $before->encrypt('first-generation', Fixture::binding())->toStorage();
        $this->assertSame('record-v1', $stored['key_id'], 'The first generation is stamped with the active key.');

        $after = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(
            new KeyRing(Fixture::key('record-v2'), [Fixture::key('record-v1')]),
        ));
        $this->assertSame(
            'first-generation',
            $after->decrypt(EncryptedEnvelope::fromStorage($stored), Fixture::binding()),
            'A pre-rotation envelope opens under the retired key.',
        );
        $this->assertSame(
            'record-v2',
            $after->encrypt('second-generation', Fixture::binding())->keyId,
            'New writes use v2.',
        );

        $resealed = $after->encrypt(
            $after->decrypt(EncryptedEnvelope::fromStorage($stored), Fixture::binding()),
            Fixture::binding(),
        );
        $this->assertSame('record-v2', $resealed->keyId, 'A re-encryption pass moves the row onto the active key.');
        $this->assertNotSame(
            $stored['ciphertext'],
            $resealed->toStorage()['ciphertext'],
            'Re-sealing changes the bytes.',
        );
    }

    /**
     * Prove a key dropped from the ring fails as unavailable and names no material or other key.
     *
     * Dropping a key from configuration is how a revocation is expressed to the in-process provider, so this is
     * also the revoked-key case.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testARevokedOrForeignKeyFailsClosedWithoutDisclosingAnything(): void
    {
        $retired = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1'))));
        $stored = $retired->encrypt('should-stay-sealed', Fixture::binding())->toStorage();
        $withoutTheKey = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v2'))));

        $error = $this->assertThrows(
            static fn (): string => $withoutTheKey->decrypt(
                EncryptedEnvelope::fromStorage($stored),
                Fixture::binding(),
            ),
            KeyUnavailable::class,
            'An envelope naming a key the ring does not hold is unavailable.',
        );
        $this->assertStringContains('"record-v1" is unavailable', $error->getMessage(), 'The requested key is named.');
        $this->assertStringExcludes(
            'should-stay-sealed',
            $error->getMessage() . $error->getTraceAsString(),
            'No plaintext.',
        );
        $this->assertStringExcludes(Fixture::key('record-v1')->material(), $error->getTraceAsString(), 'No key bytes.');
        $this->assertStringExcludes('record-v2', $error->getMessage(), 'Held identifiers are not disclosed.');
    }

    /**
     * Prove two purposes keyed apart refuse each other's envelopes as unavailable keys.
     *
     * Associated data already stops a token being read as a record. This proves the keys are separate too, which
     * is what makes a record-key rotation leave tokens alone and a token-key rotation leave records alone.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testPurposesKeyedApartRefuseEachOthersEnvelopes(): void
    {
        $records = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-encryption-v1'))));
        $plans = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('mutation-plan-v1'))));
        $token = $plans->encrypt('plan-document', 'kumwe:business-mutation-plan:v2');

        $this->assertSame(
            'plan-document',
            $plans->decrypt($token, 'kumwe:business-mutation-plan:v2'),
            'The owner opens it.',
        );
        $error = $this->assertThrows(
            static fn (): string => $records->decrypt($token, 'kumwe:business-mutation-plan:v2'),
            KeyUnavailable::class,
            'A purpose mismatch is reported as an unavailable key, never as a decryption attempt.',
        );
        $this->assertStringContains(
            '"mutation-plan-v1" is unavailable',
            $error->getMessage(),
            'The foreign key is named.',
        );
    }

    /**
     * Prove a stored row naming a weaker construction is refused by the ring cipher before a key is resolved.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testADowngradedAlgorithmIsRefusedBeforeAKeyIsResolved(): void
    {
        $cipher = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1'))));
        $stored = $cipher->encrypt('sealed', 'binding')->toStorage();
        $stored['algorithm'] = 'chacha20';
        $error = $this->assertThrows(
            static fn (): EncryptedEnvelope => EncryptedEnvelope::fromStorage($stored),
            InvalidEnvelope::class,
            'The envelope gate refuses the downgraded row.',
        );
        $this->assertStringContains('algorithm is unsupported', $error->getMessage(), 'The reason is stated.');
    }

    /**
     * Prove a provider that cannot produce its active key stops the write, and its failure carries no plaintext.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAProviderThatFailsStopsTheWriteWithoutDisclosure(): void
    {
        $unavailable = new KeyRingEnvelopeCipher(new ScriptedKeyProvider(
            Fixture::key('record-v1'),
            [],
            new KeyUnavailable('record-v1'),
        ));
        $error = $this->assertThrows(
            static fn (): EncryptedEnvelope => $unavailable->encrypt(Fixture::PLAINTEXT, 'binding'),
            KeyUnavailable::class,
            'An unavailable active key stops the write.',
        );
        $this->assertStringExcludes(
            Fixture::PLAINTEXT,
            $error->getMessage() . $error->getTraceAsString(),
            'No plaintext leaks.',
        );

        $broken = new KeyRingEnvelopeCipher(new ScriptedKeyProvider(
            Fixture::key('record-v1'),
            [],
            new RuntimeException('The key service timed out.'),
        ));
        $failure = $this->assertThrows(
            static fn (): EncryptedEnvelope => $broken->encrypt(Fixture::PLAINTEXT, 'binding'),
            RuntimeException::class,
            'Any other provider failure propagates and stops the write.',
        );
        $this->assertStringExcludes(
            Fixture::PLAINTEXT,
            $failure->getMessage() . $failure->getTraceAsString(),
            'No plaintext.',
        );
        $this->assertStringExcludes(
            substr(Fixture::PLAINTEXT, 0, 8),
            $failure->getTraceAsString(),
            'Not even a prefix.',
        );
    }

    /**
     * Prove a provider that substitutes another key for the one asked for is refused before its bytes are used.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAProviderSubstitutingAKeyIsRefusedByIdentifier(): void
    {
        $writer = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1'))));
        $envelope = $writer->encrypt('sealed', 'binding');
        $substituting = new KeyRingEnvelopeCipher(new ScriptedKeyProvider(
            Fixture::key('record-v2'),
            [],
            null,
            Fixture::key('record-v2'),
        ));
        $this->assertThrows(
            static fn (): string => $substituting->decrypt($envelope, 'binding'),
            KeyUnavailable::class,
            'A key answered under the wrong identifier is refused as unavailable.',
        );
        $sameNameWrongBytes = new KeyRingEnvelopeCipher(new ScriptedKeyProvider(
            new KeyMaterial('record-v1', Fixture::bytes('wrong')),
        ));
        $this->assertThrows(
            static fn (): string => $sameNameWrongBytes->decrypt($envelope, 'binding'),
            AuthenticationFailed::class,
            'A provider answering the right identifier with the wrong bytes fails authentication.',
        );
    }
}
