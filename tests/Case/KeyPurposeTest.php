<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Exception\InvalidKeyMaterial;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyPurpose;

/**
 * Proves a purpose carries frozen, well-formed coordinates and refuses ones that could collide or be unsafe.
 *
 * @since  0.1.0
 */
final class KeyPurposeTest extends TestCase
{
    /**
     * Prove the two coordinates a host declares today are admitted verbatim.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testHostPurposesAreCarriedVerbatim(): void
    {
        $record = new KeyPurpose('record', 'kumwe:business-record:encryption:v2', 'record-encryption-v1');
        $plan = new KeyPurpose('mutation-plan', 'kumwe:business-mutation-plan:encryption:v1', 'mutation-plan-v1');

        $this->assertSame('record', $record->name, 'The name is carried verbatim.');
        $this->assertSame('kumwe:business-record:encryption:v2', $record->derivationLabel, 'The label is verbatim.');
        $this->assertSame('record-encryption-v1', $record->defaultKeyId, 'The default identifier is verbatim.');
        $this->assertNotSame($record->derivationLabel, $plan->derivationLabel, 'Purposes derive under different labels.');
        $this->assertNotSame($record->defaultKeyId, $plan->defaultKeyId, 'Purposes stamp different identifiers.');
    }

    /**
     * Prove malformed coordinates are refused at construction.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMalformedCoordinatesAreRefused(): void
    {
        $cases = [
            'empty name' => ['', 'label:v1', 'k1', 'purpose name is invalid'],
            'name with space' => ['a b', 'label:v1', 'k1', 'purpose name is invalid'],
            'empty label' => ['record', '', 'k1', 'derivation label is invalid'],
            'label with space' => ['record', 'kumwe label', 'k1', 'derivation label is invalid'],
            'label with newline' => ['record', "kumwe\nlabel", 'k1', 'derivation label is invalid'],
            'label too long' => ['record', str_repeat('l', 256), 'k1', 'derivation label is invalid'],
            'malformed default id' => ['record', 'label:v1', '-k1', 'default key identifier is invalid'],
        ];
        foreach ($cases as $name => [$purpose, $label, $keyId, $expected]) {
            $error = $this->assertThrows(
                static fn (): KeyPurpose => new KeyPurpose($purpose, $label, $keyId),
                InvalidKeyMaterial::class,
                "The \"{$name}\" case is refused.",
            );
            $this->assertStringContains($expected, $error->getMessage(), "The \"{$name}\" refusal states its reason.");
        }
        $longest = new KeyPurpose('record', str_repeat('l', 255), 'k1');
        $this->assertSame(255, strlen($longest->derivationLabel), 'A 255-byte label is the longest admitted.');
    }
}
