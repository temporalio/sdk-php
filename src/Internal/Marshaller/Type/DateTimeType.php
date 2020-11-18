<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Type;

use Carbon\Carbon;

class DateTimeType implements TypeInterface
{
    /**
     * @var string
     */
    private string $format;

    /**
     * @param string $format
     */
    public function __construct(string $format = \DateTimeInterface::RFC3339)
    {
        $this->format = $format;
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value): \DateTimeInterface
    {
        return Carbon::parse($value);
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): string
    {
        return Carbon::parse($value)->format($this->format);
    }
}
