<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Internal\Support;

class StackRenderer
{
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

            $result[] = sprintf(
                "%s:%s",
                $line['file'] ?? '-',
                $line['line'] ?? '-'
            );
        }

        return join("\n", $result);
    }
}
