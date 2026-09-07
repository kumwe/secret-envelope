<?php

/**
 * Prove an extracted Composer archive ships exactly the reviewed consumer file set.
 *
 * The Kumwe App adoption gate reads CHARTER.md, README.md, CHANGELOG.md, MIGRATION-HANDOFF.md, docs/,
 * resources/ and src/ from the release archive, and the clean-consumer gate runs examples/ from it, so all
 * of them must be present. Development state — tests, tools, workflows, lint configuration, the lock file,
 * the vendor tree — must be absent. Every source file must be an exported symbol of the public API manifest
 * and every exported symbol must be shipped, and the archived Composer metadata must still name PHP as the
 * only runtime requirement.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
$candidate = $arguments[1] ?? null;
$root = $candidate === null ? false : realpath($candidate);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "Usage: php tools/verify-archive.php EXTRACTED_ARCHIVE_ROOT\n");
    exit(1);
}

/**
 * Decide whether a manifest member is a normalized package-relative path.
 *
 * @param   mixed  $path  Candidate path.
 *
 * @return  bool  True for a relative slash-separated path without empty, dot or parent segments.
 *
 * @since   0.1.0
 */
function archiveSafeRelative(mixed $path): bool
{
    if (!is_string($path) || $path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
        return false;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

/**
 * Decode one required JSON object file, recording a finding instead of throwing.
 *
 * @param   string        $path    Absolute file path.
 * @param   list<string>  $errors  Collected findings.
 *
 * @return  array<string, mixed>  The decoded object, empty on failure.
 *
 * @since   0.1.0
 */
function archiveJsonObject(string $path, array &$errors): array
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($bytes)) {
        $errors[] = basename($path) . ' is missing from the archive.';

        return [];
    }
    try {
        $value = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        $errors[] = basename($path) . ' is not readable JSON: ' . $error->getMessage();

        return [];
    }
    if (!is_array($value) || array_is_list($value)) {
        $errors[] = basename($path) . ' must contain a JSON object.';

        return [];
    }
    $object = [];
    foreach ($value as $member => $item) {
        $object[(string) $member] = $item;
    }

    return $object;
}

/** @var list<string> $errors */
$errors = [];
$actual = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo) {
        $errors[] = 'The archive iterator returned a non-file entry.';
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($file->isLink()) {
        $errors[] = 'The archive contains a symbolic link: ' . $relative;
        continue;
    }
    if ($file->isFile()) {
        $actual[$relative] = true;
    }
}

$requiredRoots = ['CHANGELOG.md', 'CHARTER.md', 'LICENSE', 'MIGRATION-HANDOFF.md', 'README.md', 'composer.json'];
$requiredShipped = [
    'docs/architecture.md',
    'docs/integration.md',
    'docs/public-api.md',
    'docs/releasing.md',
    'docs/security.md',
    'examples/README.md',
    'examples/round-trip.php',
    'examples/rotation.php',
    'examples/container.php',
    'resources/corpus/v1/envelopes.json',
    'docs/extraction-inventory.json',
    'resources/capabilities/v1.json',
    'resources/public-api/v1.json',
    'resources/service-map/v1.json',
    'resources/toolchain/autoload-smoke.php',
];
$forbiddenRoots = [
    '.editorconfig',
    '.gitattributes',
    '.github',
    '.gitignore',
    '.phpstan.cache',
    'composer.lock',
    'dist',
    'phpcs.xml',
    'phpstan.neon',
    'tests',
    'tools',
    'vendor',
];
$allowedRoots = [...$requiredRoots, 'docs', 'examples', 'resources', 'src'];
$expected = array_fill_keys([...$requiredRoots, ...$requiredShipped], true);

$manifest = archiveJsonObject($root . '/resources/public-api/v1.json', $errors);
$symbols = $manifest['symbols'] ?? null;
$symbolCount = 0;
if (($manifest['schema'] ?? null) !== 'kumwe-package-public-api/v1') {
    $errors[] = 'resources/public-api/v1.json does not carry the Version 2 public API schema.';
}
if (!is_array($symbols) || $symbols === []) {
    $errors[] = 'resources/public-api/v1.json exports no symbol.';
    $symbols = [];
}
foreach ($symbols as $symbol => $entry) {
    $file = is_array($entry) ? ($entry['file'] ?? null) : null;
    if (
        !is_string($symbol)
        || !str_starts_with($symbol, 'Kumwe\\Secret\\')
        || !is_string($file)
        || !archiveSafeRelative($file)
        || !str_starts_with($file, 'src/')
    ) {
        $errors[] = 'The public API manifest carries a malformed symbol entry.';
        continue;
    }
    if (isset($expected[$file])) {
        $errors[] = 'The public API manifest repeats a source path: ' . $file;
    }
    $expected[$file] = true;
    $symbolCount++;
}

foreach (array_keys($actual) as $relative) {
    $top = explode('/', $relative, 2)[0];
    if (in_array($top, $forbiddenRoots, true)) {
        $errors[] = 'The archive ships development state: ' . $relative;
    } elseif (!in_array($top, $allowedRoots, true)) {
        $errors[] = 'The archive carries an unreviewed root entry: ' . $relative;
    }
}
foreach (array_keys(array_diff_key($expected, $actual)) as $relative) {
    $errors[] = 'Required archive file is missing: ' . $relative;
}
foreach (array_keys(array_diff_key($actual, $expected)) as $relative) {
    $errors[] = 'Unexpected archive file: ' . $relative;
}

$composer = archiveJsonObject($root . '/composer.json', $errors);
if (($composer['name'] ?? null) !== 'kumwe/secret-envelope') {
    $errors[] = 'The archived composer.json does not name kumwe/secret-envelope.';
}
if (($composer['license'] ?? null) !== 'Apache-2.0') {
    $errors[] = 'The archived composer.json does not advertise the Apache-2.0 license.';
}
$runtime = is_array($composer['require'] ?? null) ? array_keys($composer['require']) : [];
if ($runtime !== ['php', 'ext-sodium', 'psr/container']) {
    $errors[] = 'The archived composer.json does not match the reviewed runtime dependencies.';
}
if (($composer['autoload'] ?? null) !== ['psr-4' => ['Kumwe\\Secret\\' => 'src/']]) {
    $errors[] = 'The archived composer.json does not autoload the one canonical namespace from src/.';
}

if ($errors !== []) {
    fwrite(STDERR, "Archive verification failed:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo sprintf(
    "Archive verified: %d files, %d public symbols, no development path shipped.\n",
    count($actual),
    $symbolCount,
);
