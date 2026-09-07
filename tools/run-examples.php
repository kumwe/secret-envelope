<?php

declare(strict_types=1);

foreach (['round-trip', 'rotation', 'container'] as $example) {
    passthru(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/examples/' . $example . '.php'),
        $status,
    );
    if ($status !== 0) {
        exit($status);
    }
}
