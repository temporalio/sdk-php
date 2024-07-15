<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Environment;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;
use Temporal\Internal\Support\DateTime;

class Environment implements EnvironmentInterface
{
    private const HEADER_REPLAY = 'replay';
    private const HEADER_TICK_TIME = 'tickTime';
    private const HEADER_RR_ID = 'rr_id';
    private const HEADER_CONTINUE_AS_NEW_SUGGESTED = 'continue_as_new_suggested';
    private const HEADER_HISTORY_LENGTH = 'history_length';
    private const HEADER_HISTORY_SIZE = 'history_size';

    /**
     * @var CarbonInterface
     */
    protected CarbonInterface $tickTime;

    /**
     * @var bool
     */
    protected bool $isReplaying = false;

    protected bool $isContinueAsNewSuggested = false;

    /** @var int<0, max> */
    protected int $historyLength = 0;

    /** @var int<0, max> */
    protected int $historySize = 0;

    /**
     * Environment constructor.
     */
    public function __construct()
    {
        $this->tickTime = new Carbon('now', new CarbonTimeZone('UTC'));
    }

    /**
     * @return \DateTimeInterface
     */
    public function now(): \DateTimeInterface
    {
        return $this->tickTime;
    }

    public function isReplaying(): bool
    {
        return $this->isReplaying;
    }


    public function isContinueAsNewSuggested(): bool
    {
        return $this->isContinueAsNewSuggested;
    }

    /**
     * @param array $headers
     */
    public function update(array $headers): void
    {
        $this->isReplaying = isset($headers[self::HEADER_REPLAY]) && $headers[self::HEADER_REPLAY] === true;

        // Intercept headers
        if (isset($headers[self::HEADER_TICK_TIME])) {
            $this->tickTime = DateTime::parse($headers[self::HEADER_TICK_TIME], new CarbonTimeZone('UTC'));
        }

        $this->isContinueAsNewSuggested = $headers[self::HEADER_CONTINUE_AS_NEW_SUGGESTED] ?? false;
        $this->historyLength = $headers[self::HEADER_HISTORY_LENGTH] ?? 0;
        $this->historySize = $headers[self::HEADER_HISTORY_SIZE] ?? 0;
    }
}
