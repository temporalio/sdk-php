<?php

declare(strict_types=1);

/**
 * Sometimes Windows CI runner fails to report test results properly.
 * This script is a workaround for that.
 */

$command = \implode(' ', \array_slice($argv, 1));
$logFile = 'runtime/phpunit.xml';

\passthru(\sprintf("%s %s --log-junit=%s 2>&1", PHP_BINARY, $command, $logFile), $code);

if (\file_exists($logFile)) {
    $xml = \simplexml_load_file($logFile);
    $failures = (int) $xml->testsuite['failures'] + (int) $xml->testsuite['errors'];

    if ($failures === 0) {
        exit(0);
    }
}

exit($code ?: 1);
