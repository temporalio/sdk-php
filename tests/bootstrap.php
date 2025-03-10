<?php

declare(strict_types=1);

\chdir(__DIR__ . '/..');
require_once 'vendor/autoload.php';

# Detect test suite or concrete test class to run
$suite = (static function (array $argv): ?string {
    $string = \implode('  ', $argv);

    # Check `--testsuite` parameter with quotes and without quotes
    if (\preg_match('/--testsuite(?:=|\s++)([^"\']\S++|\'[^\']*+\'|"[^\']*+")/', $string, $matches)) {
        return \trim($matches[1], '\'"');
    }

    # Check --filter parameter
    if (\preg_match('/--filter(?:=|\s++)([^"\']\S++|\'[^\']*+\'|"[^\']*+")/', $string, $matches)) {
        $filter = \str_replace('\\\\', '\\', \trim($matches[1], '\'"'));
        if (\preg_match('/Temporal\\\\Tests\\\\(\\w+)\\\\/', $filter, $matches)) {
            return $matches[1];
        }
    }

    # Check argument with file path
    foreach ($argv as $arg) {
        if (\is_file($arg) || \is_dir($arg)) {
            $path = \str_replace('\\', '/', $arg);
            if (\preg_match('#\\btests/(\w+)/#', $path, $matches)) {
                return $matches[1];
            }
        }
    }

    return null;
})($GLOBALS['argv'] ?? []);

# Include related bootstrap
$suite === null or (static fn(string $file) => \is_file($file) and include $file)(
    __DIR__ . DIRECTORY_SEPARATOR . $suite . DIRECTORY_SEPARATOR . 'bootstrap.php',
);
