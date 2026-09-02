<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyIdentifier;

/**
 * Proves the identifier grammar admits versioned names and refuses everything unsafe to store or log.
 *
 * @since  0.1.0
 */
final class KeyIdentifierTest extends TestCase
{
    /**
     * Prove the grammar boundary on both sides.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheGrammarAdmitsVersionedNamesAndRefusesUnsafeOnes(): void
    {
        foreach (['k', 'record-encryption-v1', 'application-secret-v1', 'a.b_c:d-e', '0', str_repeat('x', 127)] as $ok) {
            $this->assertTrue(KeyIdentifier::isValid($ok), "\"{$ok}\" is a valid identifier.");
        }
        $bad = ['', ' ', '-leading', '.leading', 'key with spaces', "tab\tbed", "new\nline", 'slash/ed', 'quote"d',
            'unicode-ключ', str_repeat('x', 128), "trailing\n", '_leading'];
        foreach ($bad as $refused) {
            $this->assertFalse(KeyIdentifier::isValid($refused), 'An unsafe identifier is refused.');
        }
        $this->assertSame(127, KeyIdentifier::MAXIMUM_LENGTH, 'The bound is 127 bytes.');
    }

    /**
     * Prove comparison is exact, and stays exact for prefixes and different lengths.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testComparisonIsExact(): void
    {
        $this->assertTrue(KeyIdentifier::equals('record-v1', 'record-v1'), 'Identical identifiers are equal.');
        $this->assertFalse(KeyIdentifier::equals('record-v1', 'record-v10'), 'A prefix is not equal.');
        $this->assertFalse(KeyIdentifier::equals('record-v1', 'Record-v1'), 'Comparison is case-sensitive.');
        $this->assertFalse(KeyIdentifier::equals('record-v1', ''), 'An empty presented identifier is not equal.');
    }
}
