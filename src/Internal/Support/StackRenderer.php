<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

class StackRenderer
{
    /**
     * Sets files and prefixes to be ignored from the stack trace.
     *
     * @var array
     */
    private static array $ignorePaths = [
        'temporal/sdk/src/Internal/',
    ];

    /**
     * @param array $files
     * @internal please consult Temporal SDK prior to use of this function.
     */
    public static function setIgnoredPaths(array $files): void
    {
        self::$ignorePaths = $files;
    }

    /**
     * Renders trace in easy to digest form, removes references to internal functionality.
     *
     * @param array $stackTrace
     * @return string
     */
    public static function renderTrace(array $stackTrace): string
    {
        $result = [];

        foreach ($stackTrace as $line) {
            if (empty($line['file'])) {
                continue;
            }

            $path = str_replace('\\', '/', $line['file']);
            foreach (self::$ignorePaths as $str) {
                if (str_contains($path, $str)) {
                    continue 2;
                }
            }

            $result[] = sprintf(
                '%s:%s',
                $line['file'] ?? '-',
                $line['line'] ?? '-'
            );
        }

        return implode("\n", $result);
    }
}
