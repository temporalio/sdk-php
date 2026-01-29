<?php

declare(strict_types=1);

/**
 * Sometimes Windows CI runner fails to report test results properly.
 * This script is a workaround for that.
 */

$command = \implode(' ', \array_slice($argv, 1));
$logFile = 'runtime/phpunit.xml';

\passthru("$command --log-junit=$logFile 2>&1", $code);

if (\file_exists($logFile)) {
    $xml = \simplexml_load_file($logFile);
    $failures = (int) $xml['failures'] + (int) $xml['errors'];

    if ($failures === 0) {
        exit(0);
    }
}

exit($code ?: 1);
