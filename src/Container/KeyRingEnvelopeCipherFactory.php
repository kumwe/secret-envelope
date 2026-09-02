<?php

declare(strict_types=1);

namespace Kumwe\Secret\Container;

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\ServiceBindingRefused;
use Psr\Container\ContainerInterface;

/**
 * Constructs the shared `KeyRingEnvelopeCipher` from the key provider the host bound.
 *
 * The factory asks the container for exactly one collaborator, the canonical `KeyProvider::class` identifier,
 * and refuses anything that does not implement the port before wiring it, so a wrong binding fails at
 * construction instead of at the first secret write. It reads no configuration, because the package declares no
 * configuration keys, and it never passes the container on. The service is shared: the cipher is immutable and
 * holds only the provider, which the host already keeps for the life of the process.
 *
 * @since  0.1.0
 */
final readonly class KeyRingEnvelopeCipherFactory
{
    /**
     * Build the cipher over the host's key provider.
     *
     * @param   ContainerInterface  $container  Container in which the host bound `KeyProvider::class`.
     *
     * @return  KeyRingEnvelopeCipher  The shared ring cipher.
     *
     * @throws  ServiceBindingRefused  When `KeyProvider::class` resolves to something that is not a key provider.
     * @throws  \Psr\Container\NotFoundExceptionInterface  When the host bound no key provider at all.
     *
     * @since   0.1.0
     */
    public function __invoke(ContainerInterface $container): KeyRingEnvelopeCipher
    {
        $keys = $container->get(KeyProvider::class);
        if (!$keys instanceof KeyProvider) {
            throw ServiceBindingRefused::forService(KeyProvider::class, KeyProvider::class, get_debug_type($keys));
        }

        return new KeyRingEnvelopeCipher($keys);
    }
}
