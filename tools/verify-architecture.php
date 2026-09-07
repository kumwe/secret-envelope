<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $count++;
    $path = substr($file->getPathname(), strlen($root) + 1);
    $code = (string) file_get_contents($file->getPathname());
    $tokens = token_get_all($code);
    $executable = '';
    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $executable .= is_array($token) ? $token[1] : $token;
    }
    if (!str_contains($executable, 'declare(strict_types=1);')) {
        $errors[] = $path . ' lacks strict types.';
    }
    $directory = dirname(substr($path, 4));
    $namespace = 'Kumwe\\Secret' . ($directory === '.' ? '' : '\\' . str_replace('/', '\\', $directory));
    if (!str_contains($executable, 'namespace ' . $namespace . ';')) {
        $errors[] = $path . ' is outside the canonical namespace.';
    }
    foreach (['Kumwe\\App\\', 'Doctrine\\', 'Illuminate\\', 'Symfony\\', 'Laminas\\'] as $forbidden) {
        if (str_contains($executable, $forbidden)) {
            $errors[] = $path . ' imports host/framework code.';
        }
    }
    if (
        str_contains($executable, 'Psr\\') && !in_array($path, [
        'src/Container/KeyRingEnvelopeCipherFactory.php', 'src/Exception/ServiceBindingRefused.php',
        ], true)
    ) {
        $errors[] = $path . ' leaks container contracts into domain code.';
    }
    if (preg_match('/\b(getenv|file_get_contents|fopen|class_alias|eval|unserialize)\s*\(/', $executable) === 1) {
        $errors[] = $path . ' reads ambient state or bypasses the boundary.';
    }
    if (preg_match('/^(?:class|readonly class|abstract class|trait) /m', $executable) === 1) {
        $errors[] = $path . ' declares a non-final implementation.';
    }
}
if ($count === 0 || $errors !== []) {
    fwrite(STDERR, "Architecture verification failed:\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "Architecture verified: {$count} portable source types.\n";
