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
use Temporal\Worker\Transport\Command\Request;

final class NewTimer extends Request
{
    protected const NAME = 'NewTimer';
    protected const CANCELLABLE = true;

    /**
     * @param \DateInterval $interval
     */
    public function __construct(\DateInterval $interval)
    {
        parent::__construct(self::NAME, ['ms' => CarbonInterval::make($interval)->totalMilliseconds]);
    }
}
