<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Exception\InvalidEnvelope;
use Kumwe\Secret\Exception\SecretException;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\EncryptedEnvelope;

/**
 * Proves a stored envelope is refused before any key is touched when its parts are wrong.
 *
 * The envelope constructor is the single gate a row passes through on the way back out of storage, so every
 * downgrade and corruption case has to be settled here rather than surfacing later as a decryption failure
 * that looks like tampering.
 *
 * @since  0.1.0
 */
final class EncryptedEnvelopeTest extends TestCase
{
    /**
     * Prove each damaged stored row is refused, for the stated reason, without echoing its bytes.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMalformedStoredEnvelopesAreRefusedBeforeAnyKeyIsUsed(): void
    {
        $nonce = base64_encode(str_repeat("\x11", EncryptedEnvelope::NONCE_BYTES));
        $ciphertext = base64_encode(str_repeat("\x22", 48));
        $well = ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'key_id' => 'k1', 'algorithm' => 'xchacha20poly1305-ietf'];
        $rows = [
            'downgraded algorithm' => [['algorithm' => 'aes-128-ecb'] + $well, 'algorithm is unsupported'],
            'empty algorithm' => [['algorithm' => ''] + $well, 'algorithm is unsupported'],
            'ciphertext is not base64' => [['ciphertext' => 'not base64!!'] + $well, 'invalid base64 data'],
            'nonce is not base64' => [['nonce' => '****'] + $well, 'invalid base64 data'],
            'encoded ciphertext is oversized' => [
                ['ciphertext' => str_repeat('A', 1_398_105)] + $well,
                'ciphertext exceeds its bound',
            ],
            'nonce is truncated' => [['nonce' => base64_encode(str_repeat("\x11", 8))] + $well, 'nonce has an invalid size'],
            'nonce is oversized' => [['nonce' => base64_encode(str_repeat("\x11", 25))] + $well, 'nonce has an invalid size'],
            'ciphertext is empty' => [['ciphertext' => ''] + $well, 'shorter than its authentication tag'],
            'ciphertext is below the tag' => [
                ['ciphertext' => base64_encode(str_repeat("\x22", 15))] + $well,
                'shorter than its authentication tag',
            ],
            'key identifier is malformed' => [['key_id' => 'key with spaces'] + $well, 'key identifier is invalid'],
            'key identifier starts with punctuation' => [['key_id' => '-leading'] + $well, 'key identifier is invalid'],
            'key identifier is empty' => [['key_id' => ''] + $well, 'key identifier is invalid'],
            'key identifier is too long' => [['key_id' => str_repeat('k', 128)] + $well, 'key identifier is invalid'],
            'ciphertext member is missing' => [array_diff_key($well, ['ciphertext' => 0]), 'no "ciphertext" member'],
            'nonce member is not a string' => [['nonce' => 12] + $well, 'no "nonce" member'],
            'key identifier member is null' => [['key_id' => null] + $well, 'no "key_id" member'],
        ];
        foreach ($rows as $name => [$storage, $expected]) {
            $error = $this->assertThrows(
                static fn (): EncryptedEnvelope => EncryptedEnvelope::fromStorage($storage),
                InvalidEnvelope::class,
                "The row \"{$name}\" must be refused as an invalid envelope.",
            );
            $this->assertInstanceOf(SecretException::class, $error, 'Every refusal carries the package marker.');
            $this->assertStringContains($expected, $error->getMessage(), "The row \"{$name}\" must state its reason.");
            foreach (['ciphertext', 'nonce'] as $member) {
                $bytes = $storage[$member] ?? null;
                if (is_string($bytes)) {
                    $this->assertStringExcludes($bytes, $error->getMessage(), 'A refusal never echoes row bytes.');
                }
            }
        }
    }

    /**
     * Prove the storage shape is deterministic, complete, text-safe and round-trips exactly.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testStorageIsDeterministicAndRoundTrips(): void
    {
        $ciphertext = str_repeat("\x22\xff", 24);
        $nonce = str_repeat("\x11", EncryptedEnvelope::NONCE_BYTES);
        $envelope = new EncryptedEnvelope($ciphertext, $nonce, 'application-secret-v1');
        $storage = $envelope->toStorage();

        $this->assertSame(['ciphertext', 'nonce', 'key_id', 'algorithm'], array_keys($storage), 'Key order is fixed.');
        $this->assertSame('xchacha20poly1305-ietf', $storage['algorithm'], 'The construction is named verbatim.');
        $this->assertSame('application-secret-v1', $storage['key_id'], 'The identifier is stored verbatim.');
        $this->assertSame(base64_encode($ciphertext), $storage['ciphertext'], 'Ciphertext is base64.');
        $this->assertSame(base64_encode($nonce), $storage['nonce'], 'The nonce is base64.');
        $this->assertSame($storage, $envelope->toStorage(), 'The same envelope always produces the same row.');
        $this->assertSame(
            json_encode($storage, JSON_THROW_ON_ERROR),
            json_encode((new EncryptedEnvelope($ciphertext, $nonce, 'application-secret-v1'))->toStorage(), JSON_THROW_ON_ERROR),
            'Two envelopes with the same parts serialize byte-identically.',
        );

        $rebuilt = EncryptedEnvelope::fromStorage($storage);
        $this->assertSame($ciphertext, $rebuilt->ciphertext, 'Ciphertext survives the round trip.');
        $this->assertSame($nonce, $rebuilt->nonce, 'The nonce survives the round trip.');
        $this->assertSame('application-secret-v1', $rebuilt->keyId, 'The identifier survives the round trip.');
        $this->assertSame(EncryptedEnvelope::ALGORITHM, $rebuilt->algorithm, 'The algorithm survives the round trip.');
        $this->assertSame(
            $storage,
            EncryptedEnvelope::fromStorage($storage + ['extra' => 'ignored'])->toStorage(),
            'Unknown row members are ignored rather than refused.',
        );
    }

    /**
     * Prove only the supported construction survives, in both directions, and the bounds hold exactly.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testOnlyTheSupportedConstructionAndBoundsAreAdmitted(): void
    {
        $nonce = str_repeat("\x11", EncryptedEnvelope::NONCE_BYTES);
        $this->assertThrows(
            static fn (): EncryptedEnvelope => new EncryptedEnvelope(str_repeat("\x22", 48), $nonce, 'k1', 'xchacha20poly1305'),
            InvalidEnvelope::class,
            'A near-miss algorithm spelling is refused.',
        );
        $atMinimum = new EncryptedEnvelope(str_repeat("\x22", EncryptedEnvelope::MINIMUM_CIPHERTEXT_BYTES), $nonce, 'k1');
        $this->assertSame(16, strlen($atMinimum->ciphertext), 'A ciphertext of exactly the tag length is admitted.');
        $atMaximum = new EncryptedEnvelope(str_repeat("\x22", EncryptedEnvelope::MAXIMUM_CIPHERTEXT_BYTES), $nonce, 'k1');
        $this->assertSame(1_048_576, strlen($atMaximum->ciphertext), 'A ciphertext of exactly one mebibyte is admitted.');
        $error = $this->assertThrows(
            static fn (): EncryptedEnvelope => new EncryptedEnvelope(
                str_repeat("\x22", EncryptedEnvelope::MAXIMUM_CIPHERTEXT_BYTES + 1),
                $nonce,
                'k1',
            ),
            InvalidEnvelope::class,
            'One byte over the bound is refused.',
        );
        $this->assertStringContains('exceeds its bound', $error->getMessage(), 'The oversize refusal states its reason.');
        $this->assertSame(24, EncryptedEnvelope::NONCE_BYTES, 'The nonce is 192 bits.');
        $this->assertSame(16, EncryptedEnvelope::MINIMUM_CIPHERTEXT_BYTES, 'The tag is 128 bits.');
    }
}
