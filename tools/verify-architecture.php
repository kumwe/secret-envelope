<?php

/**
 * Verify package dependency and container boundaries.
 */

declare(strict_types=1);

/**
 * Restrict qualified dependencies to the package, native random error and explicit container boundary.
 * @param string $source Source text, never executed.
 * @param bool $containerBoundary Whether this file owns an explicit PSR container boundary.
 * @return list<string> Boundary findings.
 */
$secretTokens = static function (string $source, bool $containerBoundary): array {
    $findings = [];
    foreach (token_get_all($source) as $token) {
        if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        if ($name === 'psr' || str_starts_with($name, 'psr\\')) {
            if (!$containerBoundary) {
                $findings[] = 'Container contract outside the container boundary: ' . $name;
            }
            continue;
        }
        if (in_array($name, ['laminas', 'twig', 'monolog', 'ramsey', 'doctrine'], true)) {
            $findings[] = 'Foreign namespace alias: ' . $name;
        }
        if (
            str_contains($name, '\\') && $name !== 'kumwe\\secret'
            && !str_starts_with($name, 'kumwe\\secret\\') && $name !== 'random\\randomexception'
        ) {
            $findings[] = 'Foreign qualified name: ' . $name;
        }
    }
    return $findings;
};

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
    $containerBoundary = in_array($path, [
        'src/Container/KeyRingEnvelopeCipherFactory.php', 'src/Exception/ServiceBindingRefused.php',
    ], true);
    foreach ($secretTokens($code, $containerBoundary) as $finding) {
        $errors[] = $path . ': ' . $finding;
    }
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
        if (stripos($executable, $forbidden) !== false) {
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

if (in_array('--self-test', $argv ?? [], true)) {
    $cases = ['new \\pSr\\Container\\ContainerInterface()', 'use PsR as Container;',
        'new \\lAmInAs\\ServiceManager()', 'use LaMiNaS as Host;', 'new \\kUmWe\\aPp\\Service()'];
    foreach ($cases as $case) {
        if ($secretTokens('<?php ' . $case . ';', false) === []) {
            throw new RuntimeException('Secret dependency mutation accepted: ' . $case);
        }
    }
    if ($secretTokens('<?php use Psr\\Container\\ContainerInterface;', true) !== []) {
        throw new RuntimeException('Explicit PSR container boundaries must remain allowed.');
    }
    if ($secretTokens('<?php use Random\\RandomException; random_bytes(24);', false) !== []) {
        throw new RuntimeException('Native nonce acquisition must remain allowed.');
    }
    echo count($cases) . " secret token mutations and two native/container controls passed.\n";
}
