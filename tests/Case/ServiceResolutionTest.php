<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\ConfigProvider;
use Kumwe\Secret\Contract\EnvelopeCipher;
use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\ServiceBindingRefused;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Tests\Support\Fixture;
use Kumwe\Secret\Tests\TestCase;
use Kumwe\Secret\Value\KeyRing;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * Proves a real Laminas ServiceManager resolves the documented services, alias and lifetime from the provider.
 *
 * This case needs the development dependency laminas/laminas-servicemanager; it fails, rather than skipping,
 * when the toolchain is not installed.
 *
 * @since  0.1.0
 */
final class ServiceResolutionTest extends TestCase
{
    /**
     * Prove resolution, aliasing and the shared lifetime against the real container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheServiceManagerResolvesTheDocumentedServices(): void
    {
        $this->assertTrue(class_exists(ServiceManager::class), 'laminas/laminas-servicemanager is installed (composer install).');
        $container = self::container(new KeyRingKeyProvider(new KeyRing(Fixture::key('record-v1'))));

        $this->assertTrue($container->has(KeyRingEnvelopeCipher::class), 'The concrete service is known.');
        $this->assertTrue($container->has(EnvelopeCipher::class), 'The port alias is known.');
        $cipher = $container->get(EnvelopeCipher::class);
        $this->assertInstanceOf(KeyRingEnvelopeCipher::class, $cipher, 'The alias resolves to the ring cipher.');
        $this->assertSame($cipher, $container->get(EnvelopeCipher::class), 'The alias is shared.');
        $this->assertSame($cipher, $container->get(KeyRingEnvelopeCipher::class), 'The concrete id shares the instance.');
        $this->assertNotSame($cipher, $container->build(KeyRingEnvelopeCipher::class), 'build() makes a fresh one.');
        if ($cipher instanceof EnvelopeCipher) {
            $envelope = $cipher->encrypt('resolved', 'binding');
            $this->assertSame('record-v1', $envelope->keyId, 'The resolved cipher uses the host-bound provider.');
            $this->assertSame('resolved', $cipher->decrypt($envelope, 'binding'), 'And round-trips.');
        }
    }

    /**
     * Prove a missing or wrong host binding fails at resolution with the typed refusals.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testMissingOrWrongHostBindingsFailAtResolution(): void
    {
        $this->assertTrue(class_exists(ServiceManager::class), 'laminas/laminas-servicemanager is installed (composer install).');
        $unbound = new ServiceManager((new ConfigProvider())->getDependencies());
        $this->assertThrows(
            static fn (): mixed => $unbound->get(EnvelopeCipher::class),
            ServiceNotFoundException::class,
            'Without a host binding for the key provider the cipher cannot be built.',
        );

        $wrong = new ServiceManager((new ConfigProvider())->getDependencies());
        $wrong->setService(KeyProvider::class, new \stdClass());
        $error = $this->assertThrows(
            static fn (): mixed => $wrong->get(EnvelopeCipher::class),
            ContainerExceptionInterface::class,
            'A wrong binding fails as a container exception.',
        );
        $this->assertTrue(self::chainContains($error, ServiceBindingRefused::class), 'The typed refusal is in the chain.');
    }

    /**
     * Build a service manager from the provider with a host-bound key provider.
     *
     * @param   KeyProvider  $provider  Host custody binding.
     *
     * @return  ServiceManager  Configured container.
     *
     * @since   0.1.0
     */
    private static function container(KeyProvider $provider): ServiceManager
    {
        $container = new ServiceManager((new ConfigProvider())->getDependencies());
        $container->setService(KeyProvider::class, $provider);

        return $container;
    }

    /**
     * Decide whether an exception or any of its causes is of a class.
     *
     * @param   Throwable     $error  Caught exception.
     * @param   class-string  $class  Class looked for.
     *
     * @return  bool  True when found in the chain.
     *
     * @since   0.1.0
     */
    private static function chainContains(Throwable $error, string $class): bool
    {
        for ($link = $error; $link !== null; $link = $link->getPrevious()) {
            if ($link instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
