<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\SodiumEnvelopeCipher;
use Kumwe\Secret\Exception\AuthenticationFailed;
use Kumwe\Secret\Exception\InvalidEnvelope;
use Kumwe\Secret\Exception\InvalidInput;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Exception\SecretException;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyMaterial;
use Throwable;

/**
 * Proves the single-key cipher seals with fresh nonces, opens exactly, and fails closed on every tampering.
 *
 * @since  0.1.0
 */
final class SodiumEnvelopeCipherTest extends TestCase
{
    /**
     * Prove an envelope round-trips and stored form never carries the plaintext.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testExactEnvelopeRoundTripsAndStorageIsNeverPlaintext(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $envelope = $cipher->encrypt('not-for-history', Fixture::binding());

        $this->assertSame('fixture-key-v1', $cipher->keyId(), 'The cipher names its key.');
        $this->assertSame('fixture-key-v1', $envelope->keyId, 'The envelope carries the key identifier.');
        $this->assertSame(EncryptedEnvelope::ALGORITHM, $envelope->algorithm, 'The envelope names the construction.');
        $this->assertSame(15 + 16, strlen($envelope->ciphertext), 'Ciphertext is the plaintext plus the tag.');
        $this->assertSame(
            'not-for-history',
            $cipher->decrypt($envelope, Fixture::binding()),
            'The value comes back exactly.',
        );
        $stored = json_encode($envelope->toStorage(), JSON_THROW_ON_ERROR);
        $this->assertStringExcludes('not-for-history', $stored, 'Stored form never carries the plaintext.');
        $this->assertStringExcludes(Fixture::key('fixture-key-v1')->material(), $stored, 'Stored form carries no key.');
        $this->assertSame(
            'not-for-history',
            $cipher->decrypt(EncryptedEnvelope::fromStorage($envelope->toStorage()), Fixture::binding()),
            'An envelope rebuilt from storage opens.',
        );
        $this->assertSame('', $cipher->decrypt($cipher->encrypt('', 'b'), 'b'), 'An empty plaintext round-trips.');
        $binary = random_bytes(512);
        $this->assertSame(
            $binary,
            $cipher->decrypt($cipher->encrypt($binary, ''), ''),
            'Binary round-trips with empty AD.',
        );
    }

    /**
     * Prove every nonce is fresh and of the right length, so equal plaintexts never yield equal envelopes.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNoncesAreFreshAndRandomOnEveryCall(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $nonces = [];
        $ciphertexts = [];
        for ($index = 0; $index < 256; $index++) {
            $envelope = $cipher->encrypt('same-value', 'same-binding');
            $this->assertSame(EncryptedEnvelope::NONCE_BYTES, strlen($envelope->nonce), 'Each nonce is 24 bytes.');
            $nonces[$envelope->nonce] = true;
            $ciphertexts[$envelope->ciphertext] = true;
        }
        $this->assertCount(
            256,
            $nonces,
            'Two hundred and fifty-six encryptions drew two hundred and fifty-six nonces.',
        );
        $this->assertCount(256, $ciphertexts, 'Equal plaintexts never produce equal ciphertexts.');
        $bytes = array_count_values(array_map(static fn (string $nonce): string => $nonce[0], array_keys($nonces)));
        $this->assertTrue(count($bytes) > 32, 'The first nonce byte varies widely rather than being constant.');
    }

    /**
     * Prove the binding is authenticated: a ciphertext moved to another place cannot be opened.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAssociatedDataPreventsCrossPlaceReplay(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $envelope = $cipher->encrypt('protected', Fixture::binding('record-a'));
        foreach (
            [
            Fixture::binding('record-b'),
            '',
            Fixture::binding('record-a') . "\n",
            'x' . Fixture::binding('record-a'),
            ] as $binding
        ) {
            $this->assertThrows(
                static fn (): string => $cipher->decrypt($envelope, $binding),
                AuthenticationFailed::class,
                'A different binding fails authentication.',
            );
        }
    }

    /**
     * Prove every single altered byte of the ciphertext and of the nonce fails authentication.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryByteOfTheCiphertextAndNonceIsAuthenticated(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $envelope = $cipher->encrypt('twelve bytes', 'binding');
        $length = strlen($envelope->ciphertext);
        for ($offset = 0; $offset < $length; $offset++) {
            foreach ([0x01, 0x80, 0xff] as $mask) {
                $tampered = $envelope->ciphertext;
                $tampered[$offset] = chr((ord($tampered[$offset]) ^ $mask) & 0xff);
                $this->assertThrows(
                    static fn (): string => $cipher->decrypt(
                        new EncryptedEnvelope($tampered, $envelope->nonce, $envelope->keyId),
                        'binding',
                    ),
                    AuthenticationFailed::class,
                    "Flipping ciphertext byte {$offset} with mask {$mask} fails authentication.",
                );
            }
        }
        for ($offset = 0; $offset < EncryptedEnvelope::NONCE_BYTES; $offset++) {
            $nonce = $envelope->nonce;
            $nonce[$offset] = chr((ord($nonce[$offset]) ^ 0x01) & 0xff);
            $this->assertThrows(
                static fn (): string => $cipher->decrypt(
                    new EncryptedEnvelope($envelope->ciphertext, $nonce, 'fixture-key-v1'),
                    'binding',
                ),
                AuthenticationFailed::class,
                "Flipping nonce byte {$offset} fails authentication.",
            );
        }
    }

    /**
     * Prove a ciphertext cut short at any length fails closed instead of returning a shortened value.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testATruncatedCiphertextFailsClosedAtEveryLength(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $envelope = $cipher->encrypt(Fixture::PLAINTEXT, 'binding');
        $length = strlen($envelope->ciphertext);
        for ($keep = $length - 1; $keep >= 0; $keep--) {
            $truncated = substr($envelope->ciphertext, 0, $keep);
            $expected = $keep < EncryptedEnvelope::MINIMUM_CIPHERTEXT_BYTES
                ? InvalidEnvelope::class
                : AuthenticationFailed::class;
            $this->assertThrows(
                static fn (): string => $cipher->decrypt(
                    new EncryptedEnvelope($truncated, $envelope->nonce, 'fixture-key-v1'),
                    'binding',
                ),
                $expected,
                "A ciphertext truncated to {$keep} bytes fails closed.",
            );
        }
        $extended = $envelope->ciphertext . "\x00";
        $this->assertThrows(
            static fn (): string => $cipher->decrypt(
                new EncryptedEnvelope($extended, $envelope->nonce, 'fixture-key-v1'),
                'binding',
            ),
            AuthenticationFailed::class,
            'A ciphertext with an appended byte fails authentication.',
        );
    }

    /**
     * Prove a foreign key identifier is reported as an unavailable key, not as a broken ciphertext, and a key
     * with the right name but different bytes is reported as a failed authentication.
     *
     * The distinction is the point: an operator reading "unavailable" restores a key, while an operator reading
     * "failed authentication" starts a tampering investigation.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testWrongKeysAreDistinguishedByIdentifierAndNeverByTrial(): void
    {
        $writer = new SodiumEnvelopeCipher(Fixture::key('retired-key-v1'));
        $envelope = $writer->encrypt(Fixture::PLAINTEXT, 'binding');

        $reader = new SodiumEnvelopeCipher(Fixture::key('active-key-v2'));
        $unavailable = $this->assertThrows(
            static fn (): string => $reader->decrypt($envelope, 'binding'),
            KeyUnavailable::class,
            'An envelope naming another key is unavailable rather than attempted.',
        );
        $this->assertStringContains(
            '"retired-key-v1" is unavailable',
            $unavailable->getMessage(),
            'The name is stated.',
        );
        $this->assertStringExcludes('authenticat', $unavailable->getMessage(), 'It is not reported as tampering.');

        $sameNameOtherBytes = new SodiumEnvelopeCipher(new KeyMaterial('retired-key-v1', Fixture::bytes('other')));
        $this->assertThrows(
            static fn (): string => $sameNameOtherBytes->decrypt($envelope, 'binding'),
            AuthenticationFailed::class,
            'A key with the right identifier but different bytes fails authentication.',
        );
    }

    /**
     * Prove the size bounds are enforced before any key material is touched, exactly at the documented limits.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testInputBoundsAreExact(): void
    {
        $cipher = new SodiumEnvelopeCipher(Fixture::key('fixture-key-v1'));
        $largest = str_repeat('x', SodiumEnvelopeCipher::MAXIMUM_PLAINTEXT_BYTES);
        $sealed = $cipher->encrypt($largest, str_repeat('a', SodiumEnvelopeCipher::MAXIMUM_ASSOCIATED_DATA_BYTES));
        $this->assertSame(1_000_016, strlen($sealed->ciphertext), 'The largest plaintext fits the envelope bound.');
        $this->assertSame(
            $largest,
            $cipher->decrypt($sealed, str_repeat('a', SodiumEnvelopeCipher::MAXIMUM_ASSOCIATED_DATA_BYTES)),
            'The largest inputs round-trip.',
        );
        $tooLong = $this->assertThrows(
            static fn (): EncryptedEnvelope => $cipher->encrypt($largest . 'x', 'binding'),
            InvalidInput::class,
            'One byte over the plaintext bound is refused.',
        );
        $this->assertStringContains('secret value exceeds', $tooLong->getMessage(), 'The plaintext refusal is named.');
        $adTooLong = $this->assertThrows(
            static fn (): EncryptedEnvelope => $cipher->encrypt('x', str_repeat('a', 4097)),
            InvalidInput::class,
            'One byte over the associated-data bound is refused on encrypt.',
        );
        $this->assertStringContains(
            'associated data exceeds',
            $adTooLong->getMessage(),
            'The binding refusal is named.',
        );
        $this->assertThrows(
            static fn (): string => $cipher->decrypt($sealed, str_repeat('a', 4097)),
            InvalidInput::class,
            'One byte over the associated-data bound is refused on decrypt.',
        );
    }

    /**
     * Prove no failure path puts the plaintext or the key into the message or the trace it reports.
     *
     * Every one of these is a message an operator, a log shipper or an error tracker may see, which is why the
     * assertion is made once over the whole set, including the chained causes and the rendered trace.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNoFailureCarriesPlaintextOrKeyMaterial(): void
    {
        $key = Fixture::key('fixture-key-v1');
        $cipher = new SodiumEnvelopeCipher($key);
        $envelope = $cipher->encrypt(Fixture::PLAINTEXT, 'record-a');
        $attempts = [
            static fn (): string => $cipher->decrypt($envelope, 'record-b'),
            static fn (): string => (new SodiumEnvelopeCipher(Fixture::key('other-key-v1')))->decrypt(
                $envelope,
                'record-a',
            ),
            static fn (): string => $cipher->decrypt(
                new EncryptedEnvelope(substr($envelope->ciphertext, 0, 20), $envelope->nonce, $envelope->keyId),
                'record-a',
            ),
            static fn (): string => $cipher->encrypt(
                Fixture::PLAINTEXT . str_repeat('x', 1_000_000),
                'record-a',
            )->ciphertext,
            static fn (): string => $cipher->encrypt(Fixture::PLAINTEXT, str_repeat('a', 4097))->ciphertext,
            static fn (): string => (new SodiumEnvelopeCipher(new KeyMaterial(
                'k',
                $key->material(),
            )))->decrypt($envelope, 'x'),
        ];
        $rendered = [];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('A fail-closed path returned a value.');
            } catch (SecretException $error) {
                $rendered[] = self::render($error);
            }
        }
        $this->assertCount(6, $rendered, 'Every attempt failed closed.');
        foreach ($rendered as $text) {
            $this->assertStringExcludes(Fixture::PLAINTEXT, $text, 'No plaintext in any failure.');
            $this->assertStringExcludes(substr(Fixture::PLAINTEXT, 0, 8), $text, 'No plaintext prefix in any failure.');
            $this->assertStringExcludes($key->material(), $text, 'No raw key in any failure.');
            $this->assertStringExcludes(substr($key->material(), 0, 8), $text, 'No raw key prefix in any failure.');
            $this->assertStringExcludes(bin2hex($key->material()), $text, 'No hexadecimal key in any failure.');
            $this->assertStringExcludes(base64_encode($key->material()), $text, 'No base64 key in any failure.');
        }
    }

    /**
     * Render an exception with its whole chain, arguments included, as a log shipper would.
     *
     * @param   Throwable  $error  Caught refusal.
     *
     * @return  string  Messages, traces and argument dumps of the chain.
     *
     * @since   0.1.0
     */
    private static function render(Throwable $error): string
    {
        $text = '';
        for ($link = $error; $link !== null; $link = $link->getPrevious()) {
            $text .= $link->getMessage() . "\n" . $link->getTraceAsString() . "\n";
            foreach ($link->getTrace() as $frame) {
                $text .= print_r($frame['args'] ?? [], true);
            }
        }

        return $text;
    }
}
