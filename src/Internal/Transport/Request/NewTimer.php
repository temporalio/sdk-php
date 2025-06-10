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
use Temporal\Internal\Workflow\AwaitOptions;
use Temporal\Worker\Transport\Command\Client\Request;

/**
 * @psalm-immutable
 */
final class NewTimer extends Request
{
    public const NAME = 'NewTimer';

    public function __construct(
        private readonly AwaitOptions $awaitOptions,
    ) {
        $options = [
            'ms' => (int) CarbonInterval::make($awaitOptions->interval)?->totalMilliseconds,
        ];
        if ($this->awaitOptions->options !== null) {
            $options['summary'] = $this->awaitOptions->options->summary;
        }

        parent::__construct(self::NAME, $options);
    }

    /**
     * @deprecated use {@see getAwaitOptions()} instead.
     */
    public function getInterval(): \DateInterval
    {
        return $this->awaitOptions->interval;
    }

    public function getAwaitOptions(): AwaitOptions
    {
        return $this->awaitOptions;
    }
}
