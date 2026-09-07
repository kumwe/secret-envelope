<?php

/**
 * Reject case-insensitive host imports and forbidden runtime escape hatches in portable package source.
 * Existing package-specific architecture and API checks remain required.
 * @since 0.1.1
 */

declare(strict_types=1);

/**
 * @param string $source PHP source to inspect without executing it.
 * @return list<string> Boundary findings.
 */
$inspect = static function (string $source): array {
    $findings = [];
    $previous = null;
    $beforePrevious = null;
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $context = $previous;
        $beforeContext = $beforePrevious;
        $beforePrevious = $previous;
        $previous = is_array($token) ? $token[0] : $token;
        if (!is_array($token)) {
            if ($token === '`') {
                $findings[] = 'Shell execution is outside the portable boundary.';
            }
            continue;
        }
        if (
            in_array($context, [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_CASE], true)
            || ($context === T_FUNCTION && $beforeContext !== T_USE)
        ) {
            continue;
        }
        if (in_array($token[0], [T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
            $findings[] = 'Runtime source loading is outside the portable boundary.';
        }
        if (!in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        foreach (['kumwe\\app', 'kumwe\\extension', 'doctrine', 'illuminate', 'symfony', 'mezzio'] as $host) {
            if ($name === $host || str_starts_with($name, $host . '\\')) {
                $findings[] = 'Host-owned namespace: ' . $name;
            }
        }
        if (
            in_array($name, [
            'ffi', 'pdo', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'dl',
            'class_alias', 'getenv', 'file_get_contents', 'file_put_contents', 'fopen',
            'class_exists', 'interface_exists', 'enum_exists', 'function_exists', 'setlocale',
            ], true)
        ) {
            $findings[] = 'Host runtime primitive: ' . $name;
        }
    }
    return $findings;
};

$root = dirname(__DIR__);
$failures = [];
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        throw new RuntimeException('Source cannot be read.');
    }
    $count++;
    foreach ($inspect($source) as $finding) {
        $failures[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $finding;
    }
}
if ($count === 0 || $failures !== []) {
    fwrite(STDERR, 'Portable token boundary failed: ' . implode('; ', $failures) . "\n");
    exit(1);
}
if (in_array('--self-test', $argv ?? [], true)) {
    $rejected = [
        'use Kumwe\\App\\Service;',
        'use kUmWe\\aPp\\Service as Alias;',
        'new \\kUmWe\\aPp\\Service();',
        'use Kumwe\\App as Host;',
        'use kUmWe\\aPp\\{Service as Alias};',
        'use dOcTrInE\\DBAL\\Connection as Storage;',
        '\\FFI::cdef("void unavailable(void);");',
        'use fFi as Native;',
        'new \\pDo("unavailable");',
        '\\SyStEm("unavailable");',
        'use function ShElL_ExEc as launch;',
        '\\GeTeNv("UNAVAILABLE");',
        '\\ClAsS_AlIaS("A", "B");',
        '\\ClAsS_ExIsTs("Unavailable");',
        'use function InTeRfAcE_ExIsTs as available;',
        '\\FuNcTiOn_ExIsTs("unavailable");',
        '\\EnUm_ExIsTs("Unavailable");',
        '\\SeTlOcAlE(LC_ALL, "unavailable");',
        'EvAl("unavailable");',
        'require "unavailable.php";',
        '`unavailable`;',
    ];
    foreach ($rejected as $code) {
        if ($inspect("<?php\n" . $code) === []) {
            throw new RuntimeException('Boundary mutation was accepted: ' . $code);
        }
    }
    if ($inspect('<?php /* use Kumwe\\App\\Service; */ $message = "FFI is unavailable";') !== []) {
        throw new RuntimeException('Comments and plain data must not become executable imports.');
    }
    $members = '<?php enum Strength { case System; } final class Guard { public static function require(): void {} }'
        . ' Guard::require(); $strength = Strength::System;';
    if ($inspect($members) !== []) {
        throw new RuntimeException('Method names and enum cases must not become global runtime primitives.');
    }
    echo count($rejected) . " hostile token cases and two inert controls passed.\n";
}
echo $count . " source files passed the portable token boundary.\n";
