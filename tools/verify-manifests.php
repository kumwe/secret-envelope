<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kumwe\Secret\ConfigProvider;

/**
 * Read a required JSON object.
 *
 * @param   string  $path  Repository-relative file.
 *
 * @return  array<string, mixed>  Object members.
 *
 * @since   0.1.0
 */
function secretManifest(string $path): array
{
    $bytes = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($bytes)) {
        throw new RuntimeException('A required manifest is missing: ' . $path);
    }
    $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('A required manifest is not an object: ' . $path);
    }
    /** @var array<string, mixed> $decoded */

    return $decoded;
}

$api = secretManifest('resources/public-api/v1.json');
$capabilities = secretManifest('resources/capabilities/v1.json');
$services = secretManifest('resources/service-map/v1.json');
$composer = secretManifest('composer.json');
$release = $api['release'];
foreach (['public-api' => $api, 'capabilities' => $capabilities, 'service-map' => $services] as $kind => $manifest) {
    if (
        $manifest['schema'] !== 'kumwe-package-' . $kind . '/v1'
        || $manifest['package'] !== 'kumwe/secret-envelope' || $manifest['release'] !== $release
    ) {
        throw new RuntimeException('Manifest identity or release mismatch: ' . $kind);
    }
}
if ($composer['require'] !== ['php' => '^8.5', 'ext-sodium' => '*', 'psr/container' => '^2.0']) {
    throw new RuntimeException('The runtime dependency ceiling changed.');
}
/** @var array<string, array<string, mixed>> $symbols */
$symbols = $api['symbols'];
/** @var list<array{id: string, symbols: list<string>, documentation: list<string>}> $entries */
$entries = $capabilities['capabilities'];
$claimed = [];
foreach ($entries as $entry) {
    foreach ($entry['symbols'] as $symbol) {
        if (!isset($symbols[$symbol]) || isset($claimed[$symbol])) {
            throw new RuntimeException('An unknown or multiply-owned capability symbol was found.');
        }
        $claimed[$symbol] = true;
    }
    foreach ($entry['documentation'] as $path) {
        if (!is_file(dirname(__DIR__) . '/' . $path)) {
            throw new RuntimeException('Capability documentation is missing: ' . $path);
        }
    }
}
if (count($claimed) !== count($symbols)) {
    throw new RuntimeException('The capabilities do not claim every public symbol.');
}
$configuration = (new ConfigProvider())->getDependencies();
if ($services['config_provider'] !== ConfigProvider::class || $services['aliases'] !== $configuration['aliases']) {
    throw new RuntimeException('The service map differs from the provider.');
}
/** @var list<array{service: string, factory: string, lifetime: string}> $factories */
$factories = $services['factories'];
$expectedFactories = [];
foreach ($factories as $factory) {
    $expectedFactories[$factory['service']] = $factory['factory'];
    if ($factory['lifetime'] !== 'shared' || ($configuration['shared'][$factory['service']] ?? false) !== true) {
        throw new RuntimeException('The service map lifetime differs from the provider.');
    }
}
if ($expectedFactories !== $configuration['factories'] || $services['configuration_keys'] !== []) {
    throw new RuntimeException('The service map factory/configuration boundary differs from the provider.');
}
echo "Manifests, ownership and actual service bindings agree.\n";
