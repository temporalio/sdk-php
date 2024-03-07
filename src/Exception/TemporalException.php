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
     * @param array<non-empty-string, mixed> $values
     * @return string
     */
    protected static function buildMessage(array $values): string
    {
        $body = '';
        $result = [];

        if (isset($values['type'], $values['message'])) {
            $body = "{$values['type']}: {$values['message']}\n";
            unset($values['type'], $values['message']);

            if (isset($values['file'], $values['line'])) {
                $body .= "in {$values['file']}:{$values['line']}\n";
                unset($values['file'], $values['line']);
            }

            $body .= "\n";
        }

        foreach ($values as $k => $value) {
            if ($value) {
                $result[] = \sprintf('%s=%s', $k, \var_export($value, true));
            }
        }

        return \rtrim($body . \implode(', ', $result));
    }
}
