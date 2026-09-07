<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/MIGRATION-HANDOFF.md';
$handoff = is_file($file) ? file_get_contents($file) : false;
if (!is_string($handoff) || !str_starts_with($handoff, "---\nschema: kumwe-migration-handoff/v2\n")) {
    throw new RuntimeException('The required Version 2 migration handoff is missing.');
}
if (preg_match('~^  pull_request: "https://github.com/kumwe/secret-envelope/pull/[0-9]+"$~m', $handoff) !== 1) {
    throw new RuntimeException('The handoff must name the observed package PR.');
}
foreach (
    [
    'source:',
    'target:',
    'ownership:',
    'framework_php:',
    'tests:',
    'next_task:',
    'concurrency:',
    'governance:',
    ] as $key
) {
    if (!str_contains($handoff, "\n" . $key . "\n")) {
        throw new RuntimeException('The handoff lacks a required block: ' . $key);
    }
}
preg_match_all('/^    - path: (\S+)\n      sha256: "([a-f0-9]{64})"$/m', $handoff, $records, PREG_SET_ORDER);
$seen = [];
foreach ($records as [, $path, $digest]) {
    if (!is_file($root . '/' . $path) || hash_file('sha256', $root . '/' . $path) !== $digest) {
        throw new RuntimeException('The handoff digest is stale: ' . $path);
    }
    $seen[$path] = true;
}
foreach (['public-api', 'capabilities', 'service-map'] as $kind) {
    if (!isset($seen['resources/' . $kind . '/v1.json'])) {
        throw new RuntimeException('The handoff does not bind every public manifest.');
    }
}
echo "Required migration handoff and manifest digests verified.\n";
