<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Container\KeyRingEnvelopeCipherFactory;
use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\ServiceBindingRefused;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\Support\ScriptedContainer;
use Kumwe\Secret\Tests\Support\ScriptedServiceNotFound;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyRing;
use Psr\Container\ContainerExceptionInterface;

/**
 * Proves the factory builds the cipher from the host's binding and refuses a wrong or missing one.
 *
 * @since  0.1.0
 */
final class KeyRingEnvelopeCipherFactoryTest extends TestCase
{
    /**
     * Prove the success path against a plain PSR-11 container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheFactoryBuildsTheCipherOverTheBoundProvider(): void
    {
        $provider = new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1')));
        $cipher = (new KeyRingEnvelopeCipherFactory())(new ScriptedContainer([KeyProvider::class => $provider]));

        $this->assertInstanceOf(KeyRingEnvelopeCipher::class, $cipher, 'The declared type is returned.');
        $envelope = $cipher->encrypt('sealed-through-the-factory', 'binding');
        $this->assertSame('record-v1', $envelope->keyId, 'The cipher seals under the bound provider\'s key.');
        $this->assertSame('sealed-through-the-factory', $cipher->decrypt($envelope, 'binding'), 'And opens it.');
    }

    /**
     * Prove the failure paths: a binding of the wrong type, and no binding at all.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testWrongOrMissingBindingsAreRefusedAtConstruction(): void
    {
        $factory = new KeyRingEnvelopeCipherFactory();
        $misconfigured = new ScriptedContainer([KeyProvider::class => 'not a provider']);
        $wrong = $this->assertThrows(
            static fn (): KeyRingEnvelopeCipher => $factory($misconfigured),
            ServiceBindingRefused::class,
            'A binding of the wrong type is refused before wiring.',
        );
        $this->assertInstanceOf(
            ContainerExceptionInterface::class,
            $wrong,
            'The refusal is a PSR-11 container exception.',
        );
        $this->assertStringContains(KeyProvider::class, $wrong->getMessage(), 'The refusal names the identifier.');
        $this->assertStringContains('string', $wrong->getMessage(), 'The refusal names the type it got.');
        $this->assertThrows(
            static fn (): KeyRingEnvelopeCipher => $factory(new ScriptedContainer()),
            ScriptedServiceNotFound::class,
            'A missing binding surfaces the container\'s own not-found refusal.',
        );
        $this->assertThrows(
            static fn (): KeyRingEnvelopeCipher => $factory(new ScriptedContainer([KeyProvider::class => null])),
            ServiceBindingRefused::class,
            'A null binding is refused.',
        );
    }
}
