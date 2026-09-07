<?php

declare(strict_types=1);

use Kumwe\Secret\Cipher\KeyRingEnvelopeCipher;
use Kumwe\Secret\ConfigProvider;
use Kumwe\Secret\Contract\EnvelopeCipher;
use Kumwe\Secret\Contract\KeyProvider;
use Kumwe\Secret\Provider\KeyRingKeyProvider;
use Kumwe\Secret\Value\KeyMaterial;
use Kumwe\Secret\Value\KeyRing;
use Laminas\ServiceManager\ServiceManager;

require_once(is_array($_SERVER['argv'] ?? null)
    ? ($_SERVER['argv'][1] ?? null)
    : null) ?? dirname(__DIR__) . '/vendor/autoload.php';

$key = new KeyMaterial('example-v1', sodium_crypto_aead_xchacha20poly1305_ietf_keygen());
$configuration = (new ConfigProvider())->getDependencies();
$configuration['services'] = [KeyProvider::class => new KeyRingKeyProvider(new KeyRing($key))];
$container = new ServiceManager($configuration);
$cipher = $container->get(EnvelopeCipher::class);
if (!$cipher instanceof KeyRingEnvelopeCipher || $cipher !== $container->get(KeyRingEnvelopeCipher::class)) {
    throw new RuntimeException('The canonical alias did not resolve the concrete cipher.');
}
if ($cipher !== $container->get(EnvelopeCipher::class)) {
    throw new RuntimeException('The documented shared lifetime was not preserved.');
}
if ($cipher->decrypt($cipher->encrypt('example value', 'example-v1'), 'example-v1') !== 'example value') {
    throw new RuntimeException('The composed cipher failed.');
}
echo "Laminas factory, alias, shared lifetime and cipher verified.\n";
