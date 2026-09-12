<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/docs/release-record.md';
$record = is_file($file) ? file_get_contents($file) : false;
if (!is_string($record) || !str_starts_with($record, "---\nschema: kumwe-package-release-record/v1\n")) {
    throw new RuntimeException('The required package release record is missing.');
}
foreach (
    [
    'source:',
    'target:',
    'ownership:',
    'framework_php:',
    'tests:',
    'consumer_contract:',
    'governance:',
    ] as $key
) {
    if (!str_contains($record, "\n" . $key . "\n")) {
        throw new RuntimeException('The release record lacks a required block: ' . $key);
    }
}
$pattern = '/^  - path: (resources\/[a-z-]+\/v1\.json)\n    sha256: ([a-f0-9]{64})$/m';
preg_match_all($pattern, $record, $records, PREG_SET_ORDER);
$seen = [];
foreach ($records as [, $path, $digest]) {
    if (isset($seen[$path]) || !is_file($root . '/' . $path) || hash_file('sha256', $root . '/' . $path) !== $digest) {
        throw new RuntimeException('The release record digest is duplicate or stale: ' . $path);
    }
    $seen[$path] = true;
}
foreach (['public-api', 'capabilities', 'service-map'] as $kind) {
    if (!isset($seen['resources/' . $kind . '/v1.json'])) {
        throw new RuntimeException('The release record does not bind every public manifest.');
    }
}
echo "Required package release record and manifest digests verified.\n";
