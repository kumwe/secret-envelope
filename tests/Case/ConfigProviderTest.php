<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Case;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\ConfigProvider;
use Kumwe\Secret\Container\KeyRingEnvelopeCipherFactory;
use Kumwe\Secret\Contract\EnvelopeCipher;
use Kumwe\Secret\Tests\TestCase;
use ReflectionClass;

/**
 * Proves the configuration is deterministic, minimal and names only final, existing package classes.
 *
 * @since  0.1.0
 */
final class ConfigProviderTest extends TestCase
{
    /**
     * Prove the shape: one factory, one alias, one shared lifetime, nothing else, identical on every call.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testTheConfigurationIsDeterministicAndMinimal(): void
    {
        $provider = new ConfigProvider();
        $config = $provider();

        $this->assertSame(['dependencies'], array_keys($config), 'Only the dependencies key is returned.');
        $this->assertSame(['factories', 'aliases', 'shared'], array_keys($config['dependencies']), 'Three sections.');
        $this->assertSame(
            [KeyRingEnvelopeCipher::class => KeyRingEnvelopeCipherFactory::class],
            $config['dependencies']['factories'],
            'Exactly one service is factory-built.',
        );
        $this->assertSame(
            [EnvelopeCipher::class => KeyRingEnvelopeCipher::class],
            $config['dependencies']['aliases'],
            'The port aliases to its default implementation.',
        );
        $this->assertSame(
            [KeyRingEnvelopeCipher::class => true],
            $config['dependencies']['shared'],
            'The cipher is shared.',
        );
        $this->assertSame($config, $provider(), 'The configuration is identical on every call.');
        $this->assertSame($config['dependencies'], $provider->getDependencies(), 'getDependencies() is the same data.');
        $this->assertSame($config, (new ConfigProvider())(), 'Two providers agree.');
    }

    /**
     * Prove every class the configuration names exists, is final and is invokable where it must be.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testEveryNamedClassExistsAndIsFinal(): void
    {
        $dependencies = (new ConfigProvider())->getDependencies();
        foreach ($dependencies['factories'] as $service => $factory) {
            $this->assertTrue(class_exists($service), "Service {$service} exists.");
            $this->assertTrue((new ReflectionClass($service))->isFinal(), "Service {$service} is final.");
            $this->assertTrue(class_exists($factory), "Factory {$factory} exists.");
            $this->assertTrue((new ReflectionClass($factory))->isFinal(), "Factory {$factory} is final.");
            $this->assertTrue(method_exists($factory, '__invoke'), "Factory {$factory} is invokable.");
            $this->assertTrue(
                str_starts_with($factory, 'Kumwe\\Secret\\Container\\'),
                'Factories live under Container.',
            );
        }
        foreach ($dependencies['aliases'] as $alias => $target) {
            $this->assertTrue(interface_exists($alias), "Alias {$alias} is a package interface.");
            $this->assertTrue(is_subclass_of($target, $alias), "Alias target {$target} implements {$alias}.");
            $this->assertTrue(isset($dependencies['factories'][$target]), "Alias target {$target} is factory-built.");
        }
        $this->assertTrue((new ReflectionClass(ConfigProvider::class))->isFinal(), 'The provider is final.');
        $this->assertSame(
            0,
            (new ReflectionClass(ConfigProvider::class))->getConstructor()?->getNumberOfParameters() ?? 0,
            'No ctor deps.',
        );
    }
}
