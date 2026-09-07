<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Render a complete PHPDoc block as readable Markdown, retaining every contract annotation.
 *
 * @param   string|false  $comment  Reflection documentation.
 *
 * @return  string  Document body.
 *
 * @since   0.1.0
 */
function secretDocumentation(string|false $comment): string
{
    if ($comment === false) {
        return '';
    }
    $lines = explode("\n", $comment);
    $body = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '/**' || $line === '*/') {
            continue;
        }
        $body[] = preg_replace('/^\* ?/', '', $line) ?? $line;
    }

    return trim(implode("\n", $body));
}

$root = dirname(__DIR__);
/** @var array{symbols: array<class-string, array<string, mixed>>} $manifest */
$manifest = json_decode(
    (string) file_get_contents($root . '/resources/public-api/v1.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$document = "# Public API\n\n"
    . "Generated from reviewed source PHPDoc and reflection; `composer api:docs` rejects documentation drift.\n\n"
    . "All values are immutable; construction validates input. No method starts a transaction, reads a clock,\n"
    . "logs secrets or performs persistence. Encryption draws randomness; provider implementations may perform\n"
    . "host-governed I/O. Shared services require stable key providers per request/job; no process synchronization\n"
    . "or PHP-string zeroization is promised. See architecture, integration and security for complete ownership.\n\n";
foreach (array_keys($manifest['symbols']) as $name) {
    $type = new ReflectionClass($name);
    $document .= '## `' . $name . "`\n\n" . secretDocumentation($type->getDocComment()) . "\n\n";
    foreach ($type->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
        if ($constant->getDeclaringClass()->getName() !== $name) {
            continue;
        }
        $document .= '### `' . $constant->getName() . "`\n\n"
            . '`' . var_export($constant->getValue(), true) . "`\n\n"
            . secretDocumentation($constant->getDocComment()) . "\n\n";
    }
    foreach ($type->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        if ($property->getDeclaringClass()->getName() !== $name) {
            continue;
        }
        $document .= '### `$' . $property->getName() . "`\n\n"
            . 'Type: `' . (string) $property->getType() . "`; readonly. The constructor documents its invariant.\n\n";
    }
    foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $name) {
            continue;
        }
        $document .= '### `' . $method->getName() . "()`\n\n```php\n"
            . ($method->isStatic() ? 'static ' : '') . $method->getName() . "(\n";
        foreach ($method->getParameters() as $parameter) {
            $default = $parameter->isDefaultValueAvailable()
                ? ' = ' . ($parameter->isDefaultValueConstant()
                    ? $parameter->getDefaultValueConstantName()
                    : var_export($parameter->getDefaultValue(), true))
                : '';
            $document .= '    ' . (string) $parameter->getType() . ' '
                . ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName() . $default . ",\n";
        }
        $returnType = $method->getReturnType();
        $document .= ')' . ($returnType instanceof ReflectionNamedType ? ': ' . $returnType->getName() : '')
            . "\n```\n\n" . secretDocumentation($method->getDocComment()) . "\n\n";
    }
}
$path = $root . '/docs/public-api.md';
if (in_array('--write', is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [], true)) {
    file_put_contents($path, $document);
} elseif (!is_file($path) || file_get_contents($path) !== $document) {
    fwrite(STDERR, "Public API documentation differs from source; review then run composer api:docs:record.\n");
    exit(1);
}
echo "Public API documentation agrees with source.\n";
