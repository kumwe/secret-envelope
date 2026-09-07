<?php

declare(strict_types=1);

namespace Kumwe\Secret;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Container\KeyRingEnvelopeCipherFactory;
use Kumwe\Secret\Contract\EnvelopeCipher;

/**
 * Deterministic Laminas/Mezzio configuration for the one injected runtime service this package exports.
 *
 * `KeyRingEnvelopeCipher` is the only service with an injected collaborator, so it is the only thing registered:
 * one explicit factory, one alias binding the `EnvelopeCipher` port to it, and a shared lifetime. Values,
 * contracts, exceptions, the single-key cipher and the in-process key provider stay out of the container; they
 * are constructed directly by whoever holds the key material, which is the host. The provider declares no
 * configuration keys, reads no environment and constructs nothing. The host binds `KeyProvider::class` to its
 * own custody adapter and registers this provider explicitly with its configuration aggregator.
 *
 * @since  0.1.0
 */
final class ConfigProvider
{
    /**
     * Return the package configuration in the shape a Mezzio configuration aggregator merges.
     *
     * @return  array{
     *              dependencies: array{
     *                  factories: array<class-string, class-string<KeyRingEnvelopeCipherFactory>>,
     *                  aliases: array<class-string, class-string>,
     *                  shared: array<class-string, bool>
     *              }
     *          }  Only this package's own services; identical on every call.
     *
     * @since   0.1.0
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->getDependencies()];
    }

    /**
     * Return the service-manager configuration alone, for a host that assembles its container by hand.
     *
     * @return  array{
     *              factories: array<class-string, class-string<KeyRingEnvelopeCipherFactory>>,
     *              aliases: array<class-string, class-string>,
     *              shared: array<class-string, bool>
     *          }  The factory, the alias and the lifetime; identical on every call.
     *
     * @since   0.1.0
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                KeyRingEnvelopeCipher::class => KeyRingEnvelopeCipherFactory::class,
            ],
            'aliases' => [
                EnvelopeCipher::class => KeyRingEnvelopeCipher::class,
            ],
            'shared' => [
                KeyRingEnvelopeCipher::class => true,
            ],
        ];
    }
}
