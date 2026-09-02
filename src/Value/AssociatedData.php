<?php

declare(strict_types=1);

namespace Kumwe\Secret\Value;

use Kumwe\Secret\Exception\InvalidInput;

/**
 * Composes the associated data that binds an envelope to the one place it belongs.
 *
 * The AEAD construction authenticates associated data without storing it, so a ciphertext lifted out of one
 * place and pasted into another no longer authenticates and cannot be opened: the database alone is not enough
 * to move a secret around. This type owns the composition rule only. A versioned domain marker opens the
 * binding, so a future change can be introduced without silently accepting envelopes written under the old one,
 * and the coordinates follow, one per line. Which coordinates identify a place is the host's policy: a record
 * host binds a field to its site, definition, record and field; a token host binds a token to its audience.
 *
 * The separator is refused inside a coordinate, because otherwise two different coordinate lists could produce
 * the same bytes and two different places would share one binding.
 *
 * @since  0.1.0
 */
final class AssociatedData
{
    /**
     * Byte that separates the marker and the coordinates.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string SEPARATOR = "\n";

    /**
     * Anchored grammar of a domain marker: the key-identifier grammar, which admits a versioned name.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string DOMAIN_PATTERN = KeyIdentifier::PATTERN;

    /**
     * Compose the binding for one place, opening with its versioned domain marker.
     *
     * The same marker and coordinates must be supplied again at decryption time or authentication fails.
     *
     * @param   string  $domain       Versioned domain marker, such as `business-record-secret-v1`, matching
     *          `DOMAIN_PATTERN`.
     * @param   string  $coordinates  Coordinates of the place, in the host's fixed order; none may contain the
     *          separator.
     *
     * @return  string  Separator-joined binding, opening with the marker; opaque to callers and never stored.
     *
     * @throws  InvalidInput  When the marker leaves its grammar or a coordinate contains the separator.
     *
     * @since   0.1.0
     */
    public static function for(string $domain, string ...$coordinates): string
    {
        if (preg_match(self::DOMAIN_PATTERN, $domain) !== 1) {
            throw new InvalidInput('An associated-data domain marker is invalid.');
        }
        foreach ($coordinates as $coordinate) {
            if (str_contains($coordinate, self::SEPARATOR)) {
                throw new InvalidInput('An associated-data coordinate cannot contain the separator.');
            }
        }

        return implode(self::SEPARATOR, [$domain, ...$coordinates]);
    }

    /**
     * Prevent instantiation; the type exists only to namespace the composition rule.
     *
     * @since  0.1.0
     */
    private function __construct()
    {
    }
}
