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
use Carbon\CarbonInterval;
use Temporal\Client\Internal\Support\DateInterval;

/**
 * @psalm-type DateIntervalFormat = DateIntervalType::FORMAT_*
 */
class DateIntervalType implements TypeInterface
{
    /**
     * @var string
     */
    private const ERROR_INVALID_DATETIME = 'Unrecognized date time interval format';

    /**
     * @var string
     */
    private const ERROR_INVALID_FORMAT = 'Invalid date interval format "%s", available formats: %s';

    /**
     * @var string
     */
    public const FORMAT_YEARS = 'y';

    /**
     * @var string
     */
    public const FORMAT_MONTHS = 'm';

    /**
     * @var string
     */
    public const FORMAT_WEEKS = 'weeks';

    /**
     * @var string
     */
    public const FORMAT_DAYS = 'days';

    /**
     * @var string
     */
    public const FORMAT_HOURS = 'hours';

    /**
     * @var string
     */
    public const FORMAT_MINUTES  = 'minutes';

    /**
     * @var string
     */
    public const FORMAT_SECONDS  = 'seconds';

    /**
     * @var string
     */
    public const FORMAT_MILLISECONDS = 'milliseconds';

    /**
     * @var string
     */
    public const FORMAT_MICROSECONDS = 'microseconds';

    /**
     * @var DateIntervalFormat[]
     */
    private const AVAILABLE_FORMATS = [
         self::FORMAT_YEARS,
         self::FORMAT_MONTHS,
         self::FORMAT_WEEKS,
         self::FORMAT_DAYS,
         self::FORMAT_HOURS,
         self::FORMAT_MINUTES,
         self::FORMAT_SECONDS,
         self::FORMAT_MILLISECONDS,
         self::FORMAT_MICROSECONDS,
    ];

    /**
     * @var string
     */
    private string $format;

    /**
     * @param DateIntervalFormat $format
     */
    public function __construct(string $format = self::FORMAT_MILLISECONDS)
    {
        if (! isset(self::AVAILABLE_FORMATS[$format])) {
            $message = \sprintf(self::ERROR_INVALID_FORMAT, $format, \implode(', ', self::AVAILABLE_FORMATS));
            throw new \InvalidArgumentException($message);
        }

        $this->format = $format;
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value): CarbonInterval
    {
        switch (true) {
            case \is_string($value):
                return CarbonInterval::fromString($value);

            case $value instanceof \DateInterval:
                return CarbonInterval::instance($value);

            case \is_int($value):
            case \is_float($value):
                $format = $this->format;
                return CarbonInterval::$format($value);

            default:
                throw new \InvalidArgumentException(self::ERROR_INVALID_DATETIME);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function serialize($value): string
    {
        $method = 'total' . \ucfirst($this->format);

        return $this->parse($value)->$method;
    }
}
