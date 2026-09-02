<?php

declare(strict_types=1);

namespace Kumwe\Secret\Value;

/**
 * The grammar and comparison rule every key identifier in the package obeys.
 *
 * A key identifier is the only thing a stored envelope records about the key that sealed it, so it has to be
 * safe to write into a column, a log line and an error message, and stable enough to be compared years later.
 * The grammar is one alphanumeric character followed by up to 126 more of `A-Za-z0-9._:-`: no whitespace, no
 * control characters, no path or quote characters, and a bounded length. An identifier names a key; it must
 * never be reused for different key material, because rotation resolves keys by identifier and never by trial.
 *
 * @since  0.1.0
 */
final class KeyIdentifier
{
    /**
     * Anchored expression an identifier must match in full.
     *
     * @var    string
     * @since  0.1.0
     */
    public const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,126}$/D';

    /**
     * Longest identifier the grammar admits, in bytes.
     *
     * @var    int
     * @since  0.1.0
     */
    public const int MAXIMUM_LENGTH = 127;

    /**
     * Decide whether a string is a well-formed key identifier.
     *
     * @param   string  $keyId  Candidate identifier.
     *
     * @return  bool  True when the whole string matches the grammar.
     *
     * @since   0.1.0
     */
    public static function isValid(string $keyId): bool
    {
        return preg_match(self::PATTERN, $keyId) === 1;
    }

    /**
     * Compare a presented identifier with a known one in constant time.
     *
     * The active identifier is compared this way on every decryption, so the common path leaks no timing
     * signal about how much of the identifier an attacker guessed.
     *
     * @param   string  $known      Identifier this process holds.
     * @param   string  $presented  Identifier read from an envelope or a caller.
     *
     * @return  bool  True when both are byte-identical.
     *
     * @since   0.1.0
     */
    public static function equals(string $known, string $presented): bool
    {
        return hash_equals($known, $presented);
    }

    /**
     * Prevent instantiation; the type exists only to name the grammar.
     *
     * @since  0.1.0
     */
    private function __construct()
    {
    }
}
