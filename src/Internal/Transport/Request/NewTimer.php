<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Carbon\CarbonInterval;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\Request;

/**
 * @psalm-immutable
 */
final class NewTimer extends Request
{
    public const NAME = 'NewTimer';

    /**
     * @param \DateInterval $interval
     */
    public function __construct(private \DateInterval $interval, HeaderInterface $header)
    {
        parent::__construct(
            name: self::NAME,
            options: ['ms' => (int)CarbonInterval::make($interval)->totalMilliseconds],
            header: $header
        );
    }

    /**
     * @return \DateInterval
     */
    public function getInterval(): \DateInterval
    {
        return $this->interval;
    }
}
