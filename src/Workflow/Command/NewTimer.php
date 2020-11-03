<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Command;

use Temporal\Client\Transport\Protocol\Command\Request;

/**
 * @psalm-type DateIntervalFormat = string|int|float|\DateInterval
 */
class NewTimer extends Request
{
    /**
     * @var string
     */
    public const NAME = 'NewTimer';

    /**
     * @param int $microseconds
     */
    public function __construct(int $microseconds)
    {
        parent::__construct(self::NAME, [
            'ms' => $microseconds,
        ]);
    }

    /**
     * @psalm-param DateIntervalFormat $interval
     *
     * @param mixed $interval
     * @return int
     * @throws \Exception
     */
    public static function parseInterval($interval): int
    {
        switch (true) {
            case \is_string($interval):
                $interval = new \DateInterval($interval);

            case $interval instanceof \DateInterval:
                return (int)($interval->f * 1000);

            case \is_int($interval):
                return $interval * 1000;

            case \is_float($interval):
                return (int)($interval * 1000);

            default:
                throw new \InvalidArgumentException('Unrecognized date interval format');
        }
    }
}
