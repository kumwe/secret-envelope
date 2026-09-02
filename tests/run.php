<?php

/**
 * Dependency-free test runner: discovers tests/Case/*Test.php, runs every public method beginning with
 * "test", and reports one line per file.
 *
 * Assertions come from Kumwe\Secret\Tests\TestCase. The Composer autoloader is used when the development
 * toolchain is installed, because the container cases need psr/container and laminas/laminas-servicemanager;
 * without it the runner registers its own PSR-4 loader and those cases fail loudly rather than being skipped.
 * Discovery fails closed: deleting the suite, or leaving a discovered case with no test methods, is a build
 * failure rather than an empty success.
 *
 * @since  0.1.0
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'Kumwe\\Secret\\Tests\\' => __DIR__ . '/',
            'Kumwe\\Secret\\' => dirname(__DIR__) . '/src/',
        ];
        foreach ($prefixes as $prefix => $base) {
            if (str_starts_with($class, $prefix)) {
                $path = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require $path;
                }

                return;
            }
        }
    });
}

$files = glob(__DIR__ . '/Case/*Test.php') ?: [];
sort($files, SORT_STRING);

if ($files === []) {
    fwrite(STDERR, "Secret envelope suite failed: no test case files were discovered.\n");
    exit(1);
}

$totalTests = 0;
$totalAssertions = 0;
$failures = [];

foreach ($files as $file) {
    $class = 'Kumwe\\Secret\\Tests\\Case\\' . basename($file, '.php');
    if (!class_exists($class) || !is_subclass_of($class, Kumwe\Secret\Tests\TestCase::class)) {
        $failures[] = "{$file} declares no {$class} extending the suite TestCase.";
        continue;
    }
    $case = new $class();
    $ran = 0;
    foreach (get_class_methods($case) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $totalTests++;
        $ran++;
        try {
            $case->{$method}();
        } catch (Throwable $error) {
            $failures[] = sprintf(
                '%s::%s - %s (%s:%d)',
                $class,
                $method,
                $error->getMessage(),
                basename($error->getFile()),
                $error->getLine(),
            );
        }
    }
    if ($ran === 0) {
        $failures[] = "{$class} declares no public test methods.";
    }
    $totalAssertions += $case->assertionCount();
    echo sprintf("%-44s %3d tests\n", basename($file), $ran);
}

if ($totalTests === 0) {
    $failures[] = 'No tests ran.';
}

if ($failures !== []) {
    fwrite(STDERR, "\nFailures:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "\nSecret envelope suite passed: {$totalTests} tests, {$totalAssertions} assertions.\n";
