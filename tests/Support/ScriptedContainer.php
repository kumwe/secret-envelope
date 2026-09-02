<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Support;

use Psr\Container\ContainerInterface;

/**
 * Test support: the smallest PSR-11 container, holding whatever a test scripts under each identifier.
 *
 * It lets the factory be proved against a container that is not Laminas, so the success and failure paths are
 * about the factory rather than about a framework. Test support only; it does not ship.
 *
 * @since  0.1.0
 */
final readonly class ScriptedContainer implements ContainerInterface
{
    /**
     * Script the bindings.
     *
     * @param   array<string, mixed>  $services  Values by identifier, exactly as the container returns them.
     *
     * @since   0.1.0
     */
    public function __construct(private array $services = [])
    {
    }

    /**
     * Return the scripted value.
     *
     * @param   string  $id  Identifier.
     *
     * @return  mixed  The scripted value.
     *
     * @throws  ScriptedServiceNotFound  When nothing is scripted under the identifier.
     *
     * @since   0.1.0
     */
    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new ScriptedServiceNotFound(sprintf('No service is bound under "%s".', $id));
        }

        return $this->services[$id];
    }

    /**
     * Decide whether an identifier is scripted.
     *
     * @param   string  $id  Identifier.
     *
     * @return  bool  True when a value is bound.
     *
     * @since   0.1.0
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
