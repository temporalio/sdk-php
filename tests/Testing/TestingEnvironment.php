<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Testing;

use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;
use Temporal\Client\Worker\Environment\Environment;

class TestingEnvironment extends Environment
{
    /**
     * @param CarbonTimeZone $zone
     * @return TestingEnvironment
     */
    public function setZone(CarbonTimeZone $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    /**
     * @param CarbonInterface $tickTime
     * @return TestingEnvironment
     */
    public function setTickTime(CarbonInterface $tickTime): self
    {
        $this->tickTime = $tickTime;

        return $this;
    }

    /**
     * @param string|null $runId
     * @return TestingEnvironment
     */
    public function setRunId(?string $runId): self
    {
        $this->runId = $runId;

        return $this;
    }

    /**
     * @param bool $isReplaying
     * @return TestingEnvironment
     */
    public function setIsReplaying(bool $isReplaying): self
    {
        $this->isReplaying = $isReplaying;

        return $this;
    }
}
