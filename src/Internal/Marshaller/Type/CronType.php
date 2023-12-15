<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

/**
 * @extends Type<string>
 */
class CronType extends Type
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE =
        'Passed value must be a type of ' .
        'cron-like string or cron expression, but %s given';

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current)
    {
        if ($value === '') {
            // by default empty cron string = no cron
            return null;
        }

        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): string
    {
        if (\is_string($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
    }
}
