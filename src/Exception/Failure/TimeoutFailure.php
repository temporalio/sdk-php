<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Failure;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\ValuesInterface;

class TimeoutFailure extends TemporalFailure
{
    /**
     * @var ValuesInterface
     */
    private ValuesInterface $lastHeartbeatDetails;

    /**
     * @var int
     */
    private int $timeoutType;

    /**
     * @param string $message
     * @param ValuesInterface $lastHeartbeatDetails
     * @param int $timeoutType
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        ValuesInterface $lastHeartbeatDetails,
        int $timeoutType,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(compact('message', 'timeoutType')),
            $message,
            $previous
        );

        $this->lastHeartbeatDetails = $lastHeartbeatDetails;
        $this->timeoutType = $timeoutType;
    }

    /**
     * @return int
     */
    public function getTimeoutType(): int
    {
        return $this->timeoutType;
    }

    /**
     * @return ValuesInterface
     */
    public function getLastHeartbeatDetails(): ValuesInterface
    {
        return $this->lastHeartbeatDetails;
    }

    /**
     * @param DataConverterInterface $converter
     */
    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->lastHeartbeatDetails->setDataConverter($converter);
    }
}
