<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests\Support;

use Kumwe\Secret\Value\AssociatedData;
use Kumwe\Secret\Value\KeyMaterial;

/**
 * Test support: deterministic fixture material assembled from readable stems.
 *
 * Every key the suite uses is the SHA-256 of a readable stem, so no line of the suite resembles a credential to
 * a secret scanner and a reader can see at a glance that nothing here was ever real key material. This class is
 * test support only; it is not part of the package's public API or capabilities and does not ship.
 *
 * @since  0.1.0
 */
final class Fixture
{
    /**
     * Plaintext no failure message and no stored row is ever allowed to contain.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string PLAINTEXT = 'correct-horse-battery-staple';

    /**
     * Derive 32 fixture bytes from a readable stem.
     *
     * @param   string  $stem  Readable stem naming what the bytes stand for.
     *
     * @return  string  Deterministic 32-byte value.
     *
     * @since   0.1.0
     */
    public static function bytes(string $stem): string
    {
        return hash('sha256', 'kumwe-secret-envelope-test:' . $stem, true);
    }

    /**
     * Build fixture key material under an identifier.
     *
     * @param   string  $keyId  Identifier the key is recorded under.
     * @param   string  $stem   Readable stem the bytes derive from; defaults to the identifier.
     *
     * @return  KeyMaterial  Deterministic fixture key.
     *
     * @since   0.1.0
     */
    public static function key(string $keyId, string $stem = ''): KeyMaterial
    {
        return new KeyMaterial($keyId, self::bytes($stem === '' ? 'key:' . $keyId : $stem));
    }

    /**
     * Compose the binding the suite seals most fixtures under.
     *
     * @param   string  $record  Record coordinate, varied to prove cross-place replay fails.
     *
     * @return  string  Versioned binding.
     *
     * @since   0.1.0
     */
    public static function binding(string $record = 'record-1'): string
    {
        return AssociatedData::for('test-domain-v1', 'site', 'definition', $record, 'field');
    }

    /**
     * Prevent instantiation; the type only namespaces the fixtures.
     *
     * @since  0.1.0
     */
    private function __construct()
    {
    }
}
