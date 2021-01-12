<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Cron\CronExpression;
use Temporal\Internal\Support\Inheritance;

class CronType extends Type implements DetectableTypeInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE =
        'Passed value must be a type of ' .
        'cron-like string or cron expression, but %s given'
    ;

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return ! $type->isBuiltin() && Inheritance::extends($type->getName(), CronExpression::class);
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current)
    {
        if (\is_string($value)) {
            return new CronExpression($value);
        }

        if ($value instanceof CronExpression) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value)
    {
        if (\is_string($value)) {
            $value = new CronExpression($value);
        }

        if (! $value instanceof CronExpression) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_INVALID_TYPE, \get_debug_type($value)));
        }

        return (string)$value->getExpression();
    }
}
