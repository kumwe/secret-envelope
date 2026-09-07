<?php

/**
 * Execute an isolated platform-failure fixture and inspect its public refusal.
 * @since 0.1.0
 */

declare(strict_types=1);

use Kumwe\Secret\Cipher\SodiumEnvelopeCipher;
use Kumwe\Secret\Exception\EncryptionFailed;
use Kumwe\Secret\Value\KeyMaterial;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/tests/Support/encryption-failure.php';

$cipher = new SodiumEnvelopeCipher(new KeyMaterial('fixture-key', str_repeat('K', 32)));
try {
    $cipher->encrypt('private-plaintext-detail', 'fixture-binding');
} catch (EncryptionFailed $error) {
    if ($error->getPrevious() !== null) {
        throw new \RuntimeException('Platform exception was chained.');
    }
    $display = (string) $error;
    foreach (['private-platform-detail', 'private-plaintext-detail', str_repeat('K', 32)] as $secret) {
        if (str_contains($display, $secret)) {
            throw new \RuntimeException('Platform failure disclosed sensitive data.');
        }
    }
    echo $error->getMessage() . "\n";
    exit(0);
}
throw new \RuntimeException('Expected the typed encryption refusal.');
