<?php

declare(strict_types=1);

namespace Kumwe\Secret\Tests;

use Countable;
use RuntimeException;
use Throwable;

/**
 * Minimal assertion base for the dependency-free suite.
 *
 * Every assertion counts itself and throws a `RuntimeException` naming the expectation on failure; the runner
 * turns that into one failure line. There is deliberately no framework here, so the behavioural suite runs on
 * any supported PHP with libsodium and nothing else.
 *
 * @since  0.1.0
 */
abstract class TestCase
{
    /**
     * Assertions made so far by this case.
     *
     * @var    int
     * @since  0.1.0
     */
    private int $assertions = 0;

    /**
     * Report how many assertions this case made.
     *
     * @return  int  Count since construction.
     *
     * @since   0.1.0
     */
    final public function assertionCount(): int
    {
        return $this->assertions;
    }

    /**
     * Require a condition to hold.
     *
     * @param   bool    $condition  Outcome under test.
     * @param   string  $message    Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the condition is false.
     *
     * @since   0.1.0
     */
    final protected function assertTrue(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /**
     * Require a condition not to hold.
     *
     * @param   bool    $condition  Outcome under test.
     * @param   string  $message    Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the condition is true.
     *
     * @since   0.1.0
     */
    final protected function assertFalse(bool $condition, string $message): void
    {
        $this->assertions++;
        if ($condition) {
            throw new RuntimeException($message);
        }
    }

    /**
     * Require strict identity between an expected and an actual value.
     *
     * @param   mixed   $expected  Expected value.
     * @param   mixed   $actual    Observed value.
     * @param   string  $message   Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the values differ.
     *
     * @since   0.1.0
     */
    final protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                '%s Expected %s, got %s.',
                $message,
                self::describe($expected),
                self::describe($actual),
            ));
        }
    }

    /**
     * Require two values to differ.
     *
     * @param   mixed   $unexpected  Value the observation must not equal.
     * @param   mixed   $actual      Observed value.
     * @param   string  $message     Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the values are identical.
     *
     * @since   0.1.0
     */
    final protected function assertNotSame(mixed $unexpected, mixed $actual, string $message): void
    {
        $this->assertions++;
        if ($unexpected === $actual) {
            throw new RuntimeException($message . ' Both were ' . self::describe($actual) . '.');
        }
    }

    /**
     * Require a substring to be present.
     *
     * @param   string  $needle    Expected fragment.
     * @param   string  $haystack  Text under test.
     * @param   string  $message   Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the fragment is absent.
     *
     * @since   0.1.0
     */
    final protected function assertStringContains(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . ' Missing: ' . $needle);
        }
    }

    /**
     * Require a substring to be absent, without echoing it when it is present.
     *
     * The forbidden fragment is usually plaintext or key material, so the failure names its length only.
     *
     * @param   string  $needle    Forbidden fragment.
     * @param   string  $haystack  Text under test.
     * @param   string  $message   Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the fragment is present.
     *
     * @since   0.1.0
     */
    final protected function assertStringExcludes(string $needle, string $haystack, string $message): void
    {
        $this->assertions++;
        if ($needle !== '' && str_contains($haystack, $needle)) {
            throw new RuntimeException(sprintf('%s A forbidden %d-byte fragment is present.', $message, strlen($needle)));
        }
    }

    /**
     * Require a value to be an instance of a class or interface.
     *
     * @param   class-string  $class    Expected type.
     * @param   mixed         $value    Value under test.
     * @param   string        $message  Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the value is not an instance.
     *
     * @since   0.1.0
     */
    final protected function assertInstanceOf(string $class, mixed $value, string $message): void
    {
        $this->assertions++;
        if (!$value instanceof $class) {
            throw new RuntimeException($message . ' Got ' . get_debug_type($value) . ' instead of ' . $class . '.');
        }
    }

    /**
     * Require an exact element count.
     *
     * @param   int                    $expected  Expected count.
     * @param   array<mixed>|Countable $value     Countable under test.
     * @param   string                 $message   Expectation, stated positively.
     *
     * @return  void
     *
     * @throws  RuntimeException  When the count differs.
     *
     * @since   0.1.0
     */
    final protected function assertCount(int $expected, array|Countable $value, string $message): void
    {
        $this->assertions++;
        if (count($value) !== $expected) {
            throw new RuntimeException(sprintf('%s Expected %d elements, counted %d.', $message, $expected, count($value)));
        }
    }

    /**
     * Require an operation to throw an instance of a class, and hand it back for further assertions.
     *
     * @param   callable(): mixed  $operation       Code expected to throw.
     * @param   class-string       $exceptionClass  Expected exception type.
     * @param   string             $message         Expectation, stated positively.
     *
     * @return  Throwable  The caught exception.
     *
     * @throws  RuntimeException  When nothing is thrown or the wrong type is thrown.
     *
     * @since   0.1.0
     */
    final protected function assertThrows(callable $operation, string $exceptionClass, string $message): Throwable
    {
        $this->assertions++;
        try {
            $operation();
        } catch (Throwable $error) {
            if (!$error instanceof $exceptionClass) {
                throw new RuntimeException(sprintf(
                    '%s Threw %s instead of %s: %s',
                    $message,
                    $error::class,
                    $exceptionClass,
                    $error->getMessage(),
                ));
            }

            return $error;
        }

        throw new RuntimeException($message . ' Nothing was thrown; expected ' . $exceptionClass . '.');
    }

    /**
     * Fail unconditionally.
     *
     * @param   string  $message  Why the case cannot continue.
     *
     * @return  never
     *
     * @throws  RuntimeException  Always.
     *
     * @since   0.1.0
     */
    final protected function fail(string $message): never
    {
        $this->assertions++;
        throw new RuntimeException($message);
    }

    /**
     * Render a value for a failure message without spilling long binary strings.
     *
     * @param   mixed  $value  Value to render.
     *
     * @return  string  Short, printable description.
     *
     * @since   0.1.0
     */
    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return strlen($value) > 40 || preg_match('/[^\x20-\x7e]/', $value) === 1
                ? sprintf('<%d-byte string>', strlen($value))
                : var_export($value, true);
        }
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return get_debug_type($value);
    }
}
