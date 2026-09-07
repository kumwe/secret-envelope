<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Cipher\SodiumEnvelopeCipher;
use Kumwe\Secret\Exception\AuthenticationFailed;
use Kumwe\Secret\Exception\InvalidEnvelope;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Exception\SecretException;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyIdentifier;
use Kumwe\Secret\Value\KeyRing;

/**
 * Property and hostile-input coverage: random values round-trip, and random damage always fails closed.
 *
 * The random source is seeded per run and the seed is printed into every failure, so a counter-example can be
 * replayed.
 *
 * @since  0.1.0
 */
final class PropertyTest extends TestCase
{
    /**
     * Prove random plaintexts and bindings of every admitted length round-trip exactly.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRandomValuesRoundTripExactly(): void
    {
        $seed = random_int(0, PHP_INT_MAX);
        mt_srand($seed);
        $cipher = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing(Fixture::key('property-v1'))));
        for ($round = 0; $round < 128; $round++) {
            $plaintext = self::randomBytes(mt_rand(0, 2048));
            $binding = self::randomBytes(mt_rand(0, 4096));
            $envelope = $cipher->encrypt($plaintext, $binding);
            $this->assertSame(
                $plaintext,
                $cipher->decrypt(EncryptedEnvelope::fromStorage($envelope->toStorage()), $binding),
                "Round {$round} (seed {$seed}) round-trips through storage.",
            );
            $this->assertSame(
                strlen($plaintext) + 16,
                strlen($envelope->ciphertext),
                "Round {$round} (seed {$seed}) length.",
            );
        }
    }

    /**
     * Prove random damage to any part of a stored row is refused with a typed package exception.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRandomDamageAlwaysFailsClosed(): void
    {
        $seed = random_int(0, PHP_INT_MAX);
        mt_srand($seed);
        $ring = new KeyRing(Fixture::key('property-v2'), [Fixture::key('property-v1')]);
        $cipher = new KeyRingEnvelopeCipher(new KeyRingKeyProvider($ring));
        $binding = 'property-binding';
        for ($round = 0; $round < 128; $round++) {
            $plaintext = self::randomBytes(mt_rand(0, 256));
            $envelope = $cipher->encrypt($plaintext, $binding);
            $storage = $envelope->toStorage();
            $member = ['ciphertext', 'nonce', 'key_id', 'algorithm', 'binding'][mt_rand(0, 4)];
            $damagedBinding = $binding;
            if ($member === 'binding') {
                $damagedBinding = self::damage($binding);
            } elseif ($member === 'ciphertext' || $member === 'nonce') {
                $storage[$member] = base64_encode(self::damage((string) base64_decode($storage[$member], true)));
            } else {
                $storage[$member] = self::damage($storage[$member]);
            }
            try {
                $result = $cipher->decrypt(EncryptedEnvelope::fromStorage($storage), $damagedBinding);
                $this->fail("Round {$round} (seed {$seed}): damaged {$member} was opened to a value.");
            } catch (SecretException $error) {
                $this->assertTrue(
                    $error instanceof AuthenticationFailed || $error instanceof InvalidEnvelope
                        || $error instanceof KeyUnavailable,
                    "Round {$round} (seed {$seed}): damaged {$member} raised " . $error::class . '.',
                );
                if ($plaintext !== '') {
                    $this->assertStringExcludes($plaintext, $error->getMessage(), 'No plaintext in the refusal.');
                }
            }
        }
    }

    /**
     * Prove random strings never slip past the identifier grammar unless they satisfy it.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testRandomIdentifiersAreClassifiedByTheGrammarAlone(): void
    {
        $seed = random_int(0, PHP_INT_MAX);
        mt_srand($seed);
        for ($round = 0; $round < 512; $round++) {
            $candidate = self::randomBytes(mt_rand(0, 140));
            $expected = $candidate !== ''
                && strlen($candidate) <= KeyIdentifier::MAXIMUM_LENGTH
                && preg_match('/^[A-Za-z0-9]/', $candidate) === 1
                && preg_match('/^[A-Za-z0-9._:-]*$/D', $candidate) === 1;
            $this->assertSame(
                $expected,
                KeyIdentifier::isValid($candidate),
                "Round {$round} (seed {$seed}) classification.",
            );
        }
        $sodium = new SodiumEnvelopeCipher(Fixture::key('property-v3'));
        $this->assertSame('property-v3', $sodium->keyId(), 'The single-key cipher names its key.');
    }

    /**
     * Draw random bytes from the seeded generator.
     *
     * @param   int  $length  Number of bytes.
     *
     * @return  string  Random bytes, reproducible from the seed.
     *
     * @since   0.1.0
     */
    private static function randomBytes(int $length): string
    {
        $bytes = '';
        for ($index = 0; $index < $length; $index++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        return $bytes;
    }

    /**
     * Damage a string in one random way that is guaranteed to change it.
     *
     * @param   string  $value  Original.
     *
     * @return  string  A different string: a flipped byte, a truncation, an appended byte or a replacement.
     *
     * @since   0.1.0
     */
    private static function damage(string $value): string
    {
        $length = strlen($value);
        $way = mt_rand(0, 3);
        if ($length === 0 || $way === 2) {
            return $value . chr(mt_rand(0, 255));
        }
        if ($way === 0) {
            $offset = mt_rand(0, $length - 1);
            $value[$offset] = chr((ord($value[$offset]) ^ (1 << mt_rand(0, 7))) & 0xff);

            return $value;
        }
        if ($way === 1) {
            return substr($value, 0, mt_rand(0, $length - 1));
        }

        return self::randomBytes(mt_rand(1, 64)) === $value ? $value . 'x' : self::randomBytes(mt_rand(1, 64));
    }
}
