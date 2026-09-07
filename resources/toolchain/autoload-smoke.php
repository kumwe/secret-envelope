<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = (is_array($_SERVER['argv'] ?? null)
    ? ($_SERVER['argv'][1] ?? null)
    : null) ?? $root . '/vendor/autoload.php';
if (!is_string($autoload) || !is_file($autoload)) {
    throw new RuntimeException('The consumer Composer autoloader is missing.');
}
$loader = require $autoload;
/** @var array{symbols: array<class-string, array<string, mixed>>} $manifest */
$manifest = json_decode(
    (string) file_get_contents($root . '/resources/public-api/v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if ($manifest['symbols'] === []) {
    throw new RuntimeException('The public API manifest exports no symbols.');
}
foreach (array_keys($manifest['symbols']) as $name) {
    if (!class_exists($name) && !interface_exists($name)) {
        throw new RuntimeException('A documented public symbol cannot autoload: ' . $name);
    }
}
foreach (['round-trip', 'rotation'] as $example) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/examples/' . $example . '.php')
        . ' ' . escapeshellarg($autoload), $status);
    if ($status !== 0) {
        exit($status);
    }
}
echo "All public symbols and runtime-only examples passed.\n";
