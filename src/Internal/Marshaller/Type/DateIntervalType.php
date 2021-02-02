<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Carbon\CarbonInterval;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Inheritance;

/**
 * @psalm-import-type DateIntervalFormat from DateInterval
 */
class DateIntervalType extends Type implements DetectableTypeInterface
{
    /**
     * @var string
     */
    private string $format;

    /**
     * @param MarshallerInterface $marshaller
     * @param string $format
     */
    public function __construct(MarshallerInterface $marshaller, string $format = DateInterval::FORMAT_NANOSECONDS)
    {
        $this->format = $format;

        parent::__construct($marshaller);
    }

    /**
     * {@inheritDoc}
     */
    public static function match(\ReflectionNamedType $type): bool
    {
        return !$type->isBuiltin() && Inheritance::extends($type->getName(), \DateInterval::class);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): int
    {
        $method = 'total' . \ucfirst($this->format);

        if ($this->format === DateInterval::FORMAT_NANOSECONDS) {
            return (int)(DateInterval::parse($value, $this->format)->totalMicroseconds * 1000);
        }

        return (int)(DateInterval::parse($value, $this->format)->$method);
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value, $current): CarbonInterval
    {
        return DateInterval::parse($value, $this->format);
    }
}
