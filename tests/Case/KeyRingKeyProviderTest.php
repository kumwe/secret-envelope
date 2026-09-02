<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyRing;

/**
 * Proves the in-process provider satisfies every clause of the adapter contract.
 *
 * @since  0.1.0
 */
final class KeyRingKeyProviderTest extends TestCase
{
    /**
     * Prove the provider answers the four questions from its ring and nothing else.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheProviderAnswersFromItsRing(): void
    {
        $active = Fixture::key('record-v2');
        $retired = Fixture::key('record-v1');
        $provider = new KeyRingKeyProvider(new KeyRing($active, [$retired]));

        $this->assertInstanceOf(KeyProvider::class, $provider, 'The provider implements the port.');
        $this->assertSame('record-v2', $provider->activeKeyId(), 'The active identifier is the ring\'s.');
        $this->assertSame($active, $provider->activeKey(), 'The active key is the ring\'s.');
        $this->assertSame($provider->activeKeyId(), $provider->activeKey()->keyId, 'Identifier and key agree.');
        $this->assertSame($retired, $provider->keyFor('record-v1'), 'A retired key resolves by identifier.');
        $this->assertSame(['record-v2', 'record-v1'], $provider->knownKeyIds(), 'Known keys are named, active first.');
        $this->assertSame($provider->activeKeyId(), $provider->activeKeyId(), 'The active identifier is stable.');
        $this->assertThrows(
            static fn (): mixed => $provider->keyFor('record-v0'),
            KeyUnavailable::class,
            'An unknown identifier fails closed.',
        );
    }
}
