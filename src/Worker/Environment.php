<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;

class Environment implements EnvironmentInterface
{
    /**
     * @var string
     */
    private const HEADER_RUN_ID = 'rid';

    /**
     * @var string
     */
    private const HEADER_REPLAY = 'replay';

    /**
     * @var string
     */
    private const HEADER_TICK_TIME = 'tickTime';

    /**
     * @var CarbonTimeZone
     */
    private CarbonTimeZone $zone;

    /**
     * @var CarbonInterface
     */
    private CarbonInterface $tickTime;

    /**
     * @var string|null
     */
    private ?string $runId = null;

    /**
     * @var bool
     */
    private bool $isReplaying = false;

    /**
     * Environment constructor.
     */
    public function __construct()
    {
        $this->zone = new CarbonTimeZone('UTC');
        $this->tickTime = new Carbon('now', $this->zone);
    }

    /**
     * @return CarbonTimeZone
     */
    public function getTimeZone(): CarbonTimeZone
    {
        return $this->zone;
    }

    /**
     * @return CarbonInterface
     */
    public function now(): CarbonInterface
    {
        return $this->tickTime;
    }

    /**
     * @return string|null
     */
    public function getRunId(): ?string
    {
        return $this->runId;
    }

    /**
     * @return bool
     */
    public function isReplaying(): bool
    {
        return $this->isReplaying;
    }

    /**
     * @param array $headers
     */
    public function update(array $headers): void
    {
        $this->runId = $headers[self::HEADER_RUN_ID] ?? null;
        $this->isReplaying = isset($headers[self::HEADER_REPLAY]) && $headers[self::HEADER_REPLAY] === true;

        // Intercept headers
        if (isset($headers[self::HEADER_TICK_TIME])) {
            $this->tickTime = Carbon::parse($headers[self::HEADER_TICK_TIME], $this->zone);
        }
    }
}
