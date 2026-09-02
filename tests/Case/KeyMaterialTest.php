<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Exception\InvalidKeyMaterial;
use Kumwe\Secret\Exception\SecretDisclosureRefused;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyMaterial;

/**
 * Proves key material admits only usable keys and never discloses its bytes through a debugging path.
 *
 * @since  0.1.0
 */
final class KeyMaterialTest extends TestCase
{
    /**
     * Prove the identifier and the key size are checked, and the refusals quote nothing.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testUnusableKeysAreRefusedWithoutDisclosure(): void
    {
        $bytes = Fixture::bytes('material');
        $cases = [
            'malformed identifier' => ['key with spaces', $bytes, 'key identifier is invalid'],
            'empty identifier' => ['', $bytes, 'key identifier is invalid'],
            'short key' => ['k1', substr($bytes, 0, 16), 'invalid size'],
            'long key' => ['k1', $bytes . 'x', 'invalid size'],
            'empty key' => ['k1', '', 'invalid size'],
        ];
        foreach ($cases as $name => [$keyId, $material, $expected]) {
            $error = $this->assertThrows(
                static fn (): KeyMaterial => new KeyMaterial($keyId, $material),
                InvalidKeyMaterial::class,
                "The \"{$name}\" case is refused.",
            );
            $this->assertStringContains($expected, $error->getMessage(), "The \"{$name}\" refusal states its reason.");
            $this->assertStringExcludes($material, $error->getMessage() . $error->getTraceAsString(), 'No key bytes.');
            $this->assertStringExcludes(bin2hex($material), $error->getMessage(), 'No hexadecimal key bytes.');
        }
        $key = new KeyMaterial('record-v1', $bytes);
        $this->assertSame('record-v1', $key->keyId, 'The identifier is public.');
        $this->assertSame($bytes, $key->material(), 'The bytes are handed out only through material().');
        $this->assertSame(32, KeyMaterial::KEY_BYTES, 'The key is 256 bits.');
    }

    /**
     * Prove key material never reaches a debug dump, a JSON encoding or a serialized string.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testKeyMaterialIsRedactedFromEveryDisclosurePath(): void
    {
        $bytes = str_repeat("\x5a", 32);
        $key = new KeyMaterial('record-v1', $bytes);

        $dump = print_r($key, true);
        $this->assertStringContains('record-v1', $dump, 'print_r discloses the identifier.');
        $this->assertStringContains('[redacted]', $dump, 'print_r shows the redaction marker.');
        $this->assertStringExcludes(str_repeat("\x5a", 8), $dump, 'print_r never shows key bytes.');

        ob_start();
        var_dump($key);
        $varDump = (string) ob_get_clean();
        $this->assertStringContains('[redacted]', $varDump, 'var_dump shows the redaction marker.');
        $this->assertStringExcludes(str_repeat("\x5a", 8), $varDump, 'var_dump never shows key bytes.');

        $json = json_encode($key, JSON_THROW_ON_ERROR);
        $this->assertSame('{"keyId":"record-v1"}', $json, 'JSON carries the identifier only.');

        $this->assertThrows(
            static fn (): string => serialize($key),
            SecretDisclosureRefused::class,
            'Serialization is refused outright.',
        );
        $this->assertSame($bytes, $key->material(), 'The refusal leaves the key usable.');
    }
}
