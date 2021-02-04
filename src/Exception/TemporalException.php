<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

class TemporalException extends \RuntimeException
{
    /**
     * Build key-value list to explain exception. Skips empty values.
     *
     * @param array $values
     * @return string
     */
    protected static function buildMessage(array $values): string
    {
        $result = [];

        foreach ($values as $k => $value) {
            if ($value) {
                $result[] = \sprintf('%s=%s', $k, \var_export($value, true));
            }
        }

        return implode(', ', $result);
    }
}
