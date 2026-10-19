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

if ($suite === null) {
    // PHPUnit runs an isolated test by piping the code into PHP, so there is nothing to detect a
    // suite from and the parent has already run the suite bootstrap. Any output of such a process
    // is reported as a test error, so only a real command line gets the notice.
    if (($GLOBALS['argv'][0] ?? '') !== 'Standard input code') {
        \fwrite(STDERR, "Test suite is not detected from the command line; the suite bootstrap is skipped.\n");
    }

    return;
}

$suite = \substr($suite, 0, \strpos($suite, '-') ?: \strlen($suite));

# Include related bootstrap
$file = __DIR__ . DIRECTORY_SEPARATOR . $suite . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (\is_file($file)) {
    include $file;
}
