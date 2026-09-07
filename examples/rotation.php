<?php

declare(strict_types=1);

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;

require_once(is_array($_SERVER['argv'] ?? null)
    ? ($_SERVER['argv'][1] ?? null)
    : null) ?? dirname(__DIR__) . '/vendor/autoload.php';

$old = new KeyMaterial('example-v1', sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
$active = new KeyMaterial('example-v2', sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
$before = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing($old)));
$after = new KeyRingEnvelopeCipher(new KeyRingKeyProvider(new KeyRing($active, [$old])));
$stored = $before->encrypt('example value', 'example-v1');
if ($after->decrypt($stored, 'example-v1') !== 'example value') {
    throw new RuntimeException('Retired-key read failed.');
}
if ($after->encrypt('example value', 'example-v1')->keyId !== 'example-v2') {
    throw new RuntimeException('New writes did not use the active key.');
}
echo "Rotation retains old reads and uses the new key for writes.\n";
