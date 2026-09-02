<?php

declare(strict_types=1);

namespace Kumwe\Secret\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Refuses to construct a package service because the host bound a collaborator of the wrong type.
 *
 * The package factories resolve their collaborators by canonical service identifier and check the result
 * before wiring it, so a container that binds `KeyProvider::class` to something that is not a key provider
 * fails at construction rather than at the first secret write. It implements the PSR-11 container exception
 * marker so a Laminas ServiceManager surfaces it unchanged instead of wrapping it.
 *
 * @since  0.1.0
 */
final class ServiceBindingRefused extends RuntimeException implements ContainerExceptionInterface, SecretException
{
    /**
     * Describe a binding that resolved to the wrong type.
     *
     * @param   string  $serviceId  Canonical service identifier the factory asked the container for.
     * @param   string  $expected   Contract the bound service had to implement.
     * @param   string  $actual     Debug type the container returned instead.
     *
     * @return  self  The refusal, naming identifiers and types only.
     *
     * @since   0.1.0
     */
    public static function forService(string $serviceId, string $expected, string $actual): self
    {
        return new self(sprintf(
            'The service "%s" must be bound to an implementation of %s; the container returned %s.',
            $serviceId,
            $expected,
            $actual,
        ));
    }
}
