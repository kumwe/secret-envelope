<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Support;

use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Exception\KeyUnavailable;
use Kumwe\Secret\Value\KeyMaterial;
use Throwable;

/**
 * Test support: a key provider whose answers and failures are scripted by the test.
 *
 * It stands in for a remote provider that fails, misbehaves or revokes, which the in-process ring never does.
 * Test support only; it does not ship and is not a package capability.
 *
 * @since  0.1.0
 */
final readonly class ScriptedKeyProvider implements KeyProvider
{
    /**
     * Script the provider.
     *
     * @param   KeyMaterial                 $active         Key reported as active.
     * @param   array<string, KeyMaterial>  $held           Keys answered by identifier, active included or not.
     * @param   ?Throwable                  $activeFailure  Exception `activeKey()` throws instead of answering.
     * @param   ?KeyMaterial                $answerAlways   Key `keyFor()` returns for every identifier, to model
     *          a provider that substitutes keys.
     *
     * @since   0.1.0
     */
    public function __construct(
        private KeyMaterial $active,
        private array $held = [],
        private ?Throwable $activeFailure = null,
        private ?KeyMaterial $answerAlways = null,
    ) {
    }

    /**
     * Name the scripted active key.
     *
     * @return  string  Its identifier.
     *
     * @since   0.1.0
     */
    public function activeKeyId(): string
    {
        return $this->active->keyId;
    }

    /**
     * Answer with the active key, or fail as scripted.
     *
     * @return  KeyMaterial  The active key.
     *
     * @throws  Throwable  The scripted failure, when one is set.
     *
     * @since   0.1.0
     */
    public function activeKey(): KeyMaterial
    {
        if ($this->activeFailure !== null) {
            throw $this->activeFailure;
        }

        return $this->active;
    }

    /**
     * Answer by identifier, or with the scripted substitute.
     *
     * @param   string  $keyId  Identifier asked for.
     *
     * @return  KeyMaterial  Held key, or the substitute when one is scripted.
     *
     * @throws  KeyUnavailable  When no key is held under the identifier and no substitute is scripted.
     *
     * @since   0.1.0
     */
    public function keyFor(string $keyId): KeyMaterial
    {
        if ($this->answerAlways !== null) {
            return $this->answerAlways;
        }
        if ($keyId === $this->active->keyId) {
            return $this->active;
        }

        return $this->held[$keyId] ?? throw new KeyUnavailable($keyId);
    }

    /**
     * Name the active key and every held key.
     *
     * @return  non-empty-list<string>  Identifiers, active first.
     *
     * @since   0.1.0
     */
    public function knownKeyIds(): array
    {
        $ids = [$this->active->keyId];
        foreach (array_keys($this->held) as $keyId) {
            if ($keyId !== $this->active->keyId) {
                $ids[] = $keyId;
            }
        }

        return $ids;
    }
}
