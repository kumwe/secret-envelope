<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;
use Throwable;

/**
 * Replays the versioned envelope corpus, so the storage format and every recorded refusal stay stable.
 *
 * The corpus holds no real secret: every key is the SHA-256 of a recorded readable stem. A vector that stops
 * opening, or a refusal that changes class, is a compatibility break that must be recorded as a new corpus
 * version rather than silently accepted.
 *
 * @since  0.1.0
 */
final class CorpusTest extends TestCase
{
    /**
     * Prove every recorded vector still opens to its plaintext under the recorded key.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryRecordedVectorStillOpens(): void
    {
        $corpus = self::corpus();
        $cipher = self::cipher($corpus);
        $this->assertSame(
            'kumwe-secret-envelope-corpus/v1',
            $corpus['schema'],
            'The corpus is the version the suite knows.',
        );
        $this->assertTrue(count($corpus['vectors']) >= 6, 'The corpus records at least six vectors.');
        foreach ($corpus['vectors'] as $vector) {
            $envelope = EncryptedEnvelope::fromStorage($vector['storage']);
            $this->assertSame(
                base64_decode($vector['plaintext_base64'], true),
                $cipher->decrypt($envelope, $vector['associated_data']),
                "Vector \"{$vector['name']}\" opens to its recorded plaintext.",
            );
            $this->assertSame($vector['key_id'], $envelope->keyId, "Vector \"{$vector['name']}\" names its key.");
        }
    }

    /**
     * Prove every recorded refusal is still refused with the recorded exception class.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryRecordedRefusalIsStillRefused(): void
    {
        $corpus = self::corpus();
        $cipher = self::cipher($corpus);
        $this->assertTrue(count($corpus['refusals']) >= 9, 'The corpus records at least nine refusals.');
        foreach ($corpus['refusals'] as $refusal) {
            try {
                $cipher->decrypt(EncryptedEnvelope::fromStorage($refusal['storage']), $refusal['associated_data']);
                $this->fail("Refusal \"{$refusal['name']}\" was opened to a value.");
            } catch (Throwable $error) {
                $this->assertSame(
                    $refusal['exception'],
                    $error::class,
                    "Refusal \"{$refusal['name']}\" raises the recorded exception class.",
                );
            }
        }
    }

    /**
     * Load and shape-check the corpus file.
     *
     * @return  array{
     *              schema: string,
     *              keys: array<string, array{stem: string}>,
     *              vectors: list<array{
     *                  name: string, key_id: string, associated_data: string, plaintext_base64: string,
     *                  storage: array<string, mixed>
     *              }>,
     *              refusals: list<array{
     *                  name: string, associated_data: string, storage: array<string, mixed>, exception: string
     *              }>
     *          }  Decoded corpus.
     *
     * @since   0.1.0
     */
    private static function corpus(): array
    {
        $path = dirname(__DIR__, 2) . '/resources/corpus/v1/envelopes.json';
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException('The corpus file is unreadable.');
        }
        /** @var array{
         *     schema: string,
         *     keys: array<string, array{stem: string}>,
         *     vectors: list<array{
         *         name: string, key_id: string, associated_data: string, plaintext_base64: string,
         *         storage: array<string, mixed>
         *     }>,
         *     refusals: list<array{
         *         name: string, associated_data: string, storage: array<string, mixed>, exception: string
         *     }>
         * } $corpus */
        $corpus = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR);

        return $corpus;
    }

    /**
     * Build the record-purpose ring cipher the corpus was sealed with, from the recorded stems.
     *
     * @param   array{keys: array<string, array{stem: string}>}  $corpus  Decoded corpus.
     *
     * @return  KeyRingEnvelopeCipher  Cipher over the recorded record keys, the highest version active.
     *
     * @since   0.1.0
     */
    private static function cipher(array $corpus): KeyRingEnvelopeCipher
    {
        $keys = [];
        foreach ($corpus['keys'] as $keyId => $key) {
            if (str_starts_with($keyId, 'corpus-record-')) {
                $keys[$keyId] = new KeyMaterial($keyId, hash('sha256', $key['stem'], true));
            }
        }
        krsort($keys, SORT_STRING);
        $active = array_shift($keys);
        if (!$active instanceof KeyMaterial) {
            throw new \RuntimeException('The corpus records no record-purpose key.');
        }

        return new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing($active, array_values($keys))));
    }
}
