<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Exception\InvalidKeyMaterial;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyRing;

/**
 * Proves the ring resolves keys by identifier only, orders them predictably and refuses ambiguity.
 *
 * @since  0.1.0
 */
final class KeyRingTest extends TestCase
{
    /**
     * Numeric identifiers remain strings even when PHP coerces their lookup-array keys to integers.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testNumericIdentifiersKeepTheirPublicStringType(): void
    {
        $retired = Fixture::key('12');
        $ring = new KeyRing(Fixture::key('active'), [$retired, Fixture::key('2'), Fixture::key('001')]);

        $this->assertSame(['active', '001', '12', '2'], $ring->keyIds(), 'String order and type are preserved.');
        $this->assertSame($retired, $ring->keyFor('12'), 'Numeric key identifiers still resolve.');
    }

    /**
     * Prove resolution by identifier, active first, retired in ascending order.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testKeysResolveByIdentifierOnly(): void
    {
        $active = Fixture::key('record-v3');
        $older = Fixture::key('record-v1');
        $old = Fixture::key('record-v2');
        $ring = new KeyRing($active, [$old, $older]);

        $this->assertSame(['record-v3', 'record-v1', 'record-v2'], $ring->keyIds(), 'Active first, then ascending.');
        $this->assertSame($active, $ring->keyFor('record-v3'), 'The active key resolves.');
        $this->assertSame($older, $ring->keyFor('record-v1'), 'A retired key resolves by name.');
        $this->assertSame($old, $ring->keyFor('record-v2'), 'Another retired key resolves by name.');
        $this->assertSame($active, $ring->active, 'The active key is exposed.');
        $this->assertSame(['record-v9'], (new KeyRing(Fixture::key('record-v9')))->keyIds(), 'A ring may hold one key.');
    }

    /**
     * Prove a key the ring does not hold fails closed, naming only what was asked for.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAMissingKeyIsUnavailableAndNamesNothingElse(): void
    {
        $ring = new KeyRing(Fixture::key('record-v2'), [Fixture::key('record-v1')]);
        $error = $this->assertThrows(
            static fn (): mixed => $ring->keyFor('record-v0'),
            KeyUnavailable::class,
            'A dropped or foreign key is unavailable, not attempted.',
        );
        $this->assertInstanceOf(KeyUnavailable::class, $error, 'The typed refusal is raised.');
        $this->assertStringContains('"record-v0" is unavailable', $error->getMessage(), 'The requested name is stated.');
        $this->assertStringExcludes('record-v1', $error->getMessage(), 'Held identifiers are not disclosed.');
        $this->assertStringExcludes('record-v2', $error->getMessage(), 'The active identifier is not disclosed.');
        if ($error instanceof KeyUnavailable) {
            $this->assertSame('record-v0', $error->keyId(), 'The requested identifier is exposed for routing.');
        }
        $malformed = $this->assertThrows(
            static fn (): mixed => $ring->keyFor("record\nv0"),
            KeyUnavailable::class,
            'A malformed identifier is unavailable too.',
        );
        $this->assertStringContains('"unnamed" is unavailable', $malformed->getMessage(), 'It is not echoed.');
        $this->assertThrows(
            static fn (): mixed => $ring->keyFor('record-v'),
            KeyUnavailable::class,
            'A prefix of the active identifier does not resolve.',
        );
    }

    /**
     * Prove duplicate identifiers, non-key entries and an overfull ring are refused at construction.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testAmbiguousOrOverfullRingsAreRefused(): void
    {
        $active = Fixture::key('record-v1');
        $error = $this->assertThrows(
            static fn (): KeyRing => new KeyRing($active, [Fixture::key('record-v1', 'other-bytes')]),
            InvalidKeyMaterial::class,
            'A retired key repeating the active identifier is refused.',
        );
        $this->assertStringContains('one identifier twice', $error->getMessage(), 'The reason is stated.');
        $this->assertThrows(
            static fn (): KeyRing => new KeyRing($active, [Fixture::key('record-v0'), Fixture::key('record-v0', 'b')]),
            InvalidKeyMaterial::class,
            'Two retired keys under one identifier are refused.',
        );
        $this->assertThrows(
            /** @phpstan-ignore argument.type (a hostile entry is the point of the case) */
            static fn (): KeyRing => new KeyRing($active, ['not-a-key']),
            InvalidKeyMaterial::class,
            'A retired entry that is not key material is refused.',
        );
        $retired = [];
        for ($index = 0; $index < KeyRing::MAXIMUM_RETIRED_KEYS; $index++) {
            $retired[] = Fixture::key('retired-' . $index);
        }
        $this->assertCount(33, (new KeyRing($active, $retired))->keyIds(), 'Thirty-two retired keys are admitted.');
        $retired[] = Fixture::key('retired-overflow');
        $overflow = $this->assertThrows(
            static fn (): KeyRing => new KeyRing($active, $retired),
            InvalidKeyMaterial::class,
            'A thirty-third retired key is refused.',
        );
        $this->assertStringContains('at most 32', $overflow->getMessage(), 'The bound is stated.');
    }
}
