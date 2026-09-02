<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Exception\InvalidInput;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\AssociatedData;

/**
 * Proves the binding is composed byte-exactly and cannot be made ambiguous.
 *
 * @since  0.1.0
 */
final class AssociatedDataTest extends TestCase
{
    /**
     * Prove the composition reproduces the bytes a record host has always sealed under.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheBindingIsByteExact(): void
    {
        $binding = AssociatedData::for(
            'business-record-secret-v1',
            'default',
            '00000000-0000-4000-8000-000000000001',
            'record-key',
            'credential',
        );

        $this->assertSame(
            "business-record-secret-v1\ndefault\n00000000-0000-4000-8000-000000000001\nrecord-key\ncredential",
            $binding,
            'The marker opens the binding and each coordinate follows on its own line.',
        );
        $this->assertSame('marker-v1', AssociatedData::for('marker-v1'), 'A marker alone is a valid binding.');
        $this->assertSame("marker-v1\n\nb", AssociatedData::for('marker-v1', '', 'b'), 'An empty coordinate is kept.');
        $this->assertSame("\n", AssociatedData::SEPARATOR, 'The separator is a newline.');
    }

    /**
     * Prove a coordinate carrying the separator is refused, so two places can never share one binding.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAmbiguousCoordinatesAndMalformedMarkersAreRefused(): void
    {
        $error = $this->assertThrows(
            static fn (): string => AssociatedData::for('marker-v1', "a\nb", 'c'),
            InvalidInput::class,
            'A coordinate containing the separator is refused.',
        );
        $this->assertStringContains('cannot contain the separator', $error->getMessage(), 'The reason is stated.');
        $this->assertNotSame(
            AssociatedData::for('marker-v1', 'a', 'b', 'c'),
            AssociatedData::for('marker-v1', 'a', 'b'),
            'Different coordinate lists produce different bindings.',
        );
        foreach (['', ' marker', 'marker v1', "marker\n", '-marker', 'marker/v1'] as $marker) {
            $refused = $this->assertThrows(
                static fn (): string => AssociatedData::for($marker, 'a'),
                InvalidInput::class,
                'A malformed domain marker is refused.',
            );
            $this->assertStringContains('domain marker is invalid', $refused->getMessage(), 'The reason is stated.');
        }
    }
}
