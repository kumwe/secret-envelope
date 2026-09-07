<?php

declare(strict_types=1);

use Kumwe\Secret\Cipher\SodiumEnvelopeCipher;
use Kumwe\Secret\Value\AssociatedData;
use Kumwe\Secret\Value\EncryptedEnvelope;
use Kumwe\Secret\Value\KeyMaterial;

require_once(is_array($_SERVER['argv'] ?? null)
    ? ($_SERVER['argv'][1] ?? null)
    : null) ?? dirname(__DIR__) . '/vendor/autoload.php';

$key = new KeyMaterial('example-v1', sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
$cipher = new SodiumEnvelopeCipher($key);
$binding = AssociatedData::for('example-v1', 'site', 'record', 'field');
$storage = $cipher->encrypt('example value', $binding)->toStorage();
if ($cipher->decrypt(EncryptedEnvelope::fromStorage($storage), $binding) !== 'example value') {
    throw new RuntimeException('The envelope round trip failed.');
}
echo "Authenticated envelope round trip passed.\n";
