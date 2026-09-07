<?php

/**
 * Enforce complete member documentation across the library and its suite.
 *
 * Every documentable member, that is every class-like declaration, method, non-promoted property, class
 * constant and enum case, must carry a documentation block whose last tag is `@since`; a method's block must
 * name every parameter, promoted properties included, with an `@param` line, and every method but a constructor
 * must declare `@return`. A convention without a gate is a suggestion; this is the gate, and it runs in
 * `composer check` and CI so an undocumented member fails the build.
 *
 * Dependency-free line scanner: deliberate simplicity over a full parser, matching how the library is written
 * (one member per declaration line, PSR-12 shapes). A shape it cannot classify fails closed.
 *
 * @since  0.1.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$members = 0;

$paths = [];
foreach (['src', 'tests'] as $directory) {
    if (!is_dir($root . '/' . $directory)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }
}
sort($paths);

foreach ($paths as $path) {
    $relative = substr($path, strlen($root) + 1);
    $lines = file($path);
    if ($lines === false) {
        $failures[] = "{$relative} cannot be read.";
        continue;
    }
    $count = count($lines);

    for ($index = 0; $index < $count; $index++) {
        $line = $lines[$index];

        $isClassLike = preg_match(
            '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum|trait)\s+\w+/',
            $line,
        ) === 1;
        $isFunction = preg_match(
            '/^\s*(?:final\s+|abstract\s+)?(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)/',
            $line,
            $functionMatch,
        ) === 1;
        $isConstant = preg_match('/^\s*(?:final\s+)?(?:public|protected|private)\s+const\s/', $line) === 1;
        $isEnumCase = preg_match('/^\s*case\s+\w+(\s*=|\s*;)/', $line) === 1;
        $isProperty = !$isFunction && !$isConstant && preg_match(
            '/^\s*(?:public|protected|private)\s+(?:static\s+)?(?:readonly\s+)?[?\\\\\w|]+\s+\$\w+\s*[;=]/',
            $line,
        ) === 1;

        if (!$isClassLike && !$isFunction && !$isConstant && !$isEnumCase && !$isProperty) {
            continue;
        }

        // Promoted constructor parameters are covered by the constructor's own block; they are indented
        // inside its signature and end with a comma or closing parenthesis rather than a semicolon.
        if ($isProperty && preg_match('/[,)]\s*$/', rtrim($line)) === 1) {
            continue;
        }

        $members++;
        $where = "{$relative}:" . ($index + 1);

        $back = $index - 1;
        while ($back >= 0 && preg_match('/^\s*(#\[.*\]\s*)?$/', $lines[$back]) === 1) {
            $back--;
        }
        if ($back < 0 || !str_contains($lines[$back], '*/')) {
            $failures[] = "{$where} has no documentation block.";
            continue;
        }

        $block = '';
        $blockLines = [];
        for ($start = $back; $start >= 0; $start--) {
            $block = $lines[$start] . $block;
            array_unshift($blockLines, rtrim($lines[$start]));
            if (str_contains($lines[$start], '/**')) {
                break;
            }
        }
        if (preg_match('/@since\s+\d+\.\d+\.\d+/', $block) !== 1) {
            $failures[] = "{$where} documentation block has no @since tag.";
        }
        $tags = array_values(array_filter(
            $blockLines,
            static fn (string $l): bool => preg_match('/^\s*\*\s*@/', $l) === 1,
        ));
        if ($tags !== [] && preg_match('/^\s*\*\s*@since\b/', $tags[count($tags) - 1]) !== 1) {
            $failures[] = "{$where} documentation block must end with its @since tag.";
        }

        if ($isFunction) {
            $signature = $line;
            $forward = $index;
            while (!str_contains($signature, ')') && ++$forward < $count) {
                $signature .= $lines[$forward];
            }
            $open = (int) strpos($signature, '(');
            $close = (int) strrpos($signature, ')');
            $inside = preg_replace('/=\s*[^,)]+/', '', substr($signature, $open, $close - $open)) ?? '';
            preg_match_all('/\$(\w+)/', $inside, $parameters);
            foreach ($parameters[1] as $parameter) {
                if (preg_match('/@param\s+[^$]*\$' . preg_quote($parameter, '/') . '\b/', $block) !== 1) {
                    $failures[] = "{$where} block documents no @param for \${$parameter}.";
                }
            }
            if ($functionMatch[1] !== '__construct' && preg_match('/@return\s+\S/', $block) !== 1) {
                $failures[] = "{$where} block declares no @return.";
            }
            if ($functionMatch[1] === '__construct' && preg_match('/@return\b/', $block) === 1) {
                $failures[] = "{$where} constructor block must not declare @return.";
            }
        }
    }
}

if ($members === 0) {
    $failures[] = 'No documentable members were found.';
}

if ($failures !== []) {
    fwrite(STDERR, 'Member documentation is incomplete (' . count($failures) . " finding(s)):\n");
    foreach (array_slice($failures, 0, 60) as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    if (count($failures) > 60) {
        fwrite(STDERR, ' - ... and ' . (count($failures) - 60) . " more.\n");
    }
    exit(1);
}

echo "Member documentation complete: {$members} members across " . count($paths) . " files.\n";
