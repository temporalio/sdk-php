<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\ValuesInterface;

class TimeoutFailure extends TemporalFailure
{
    private ValuesInterface $lastHeartbeatDetails;
    private int $timeoutType;

    /**
     * @param string $message
     * @param ValuesInterface $lastHeartbeatDetails
     * @param int $timeoutWorkflowType
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        ValuesInterface $lastHeartbeatDetails,
        int $timeoutWorkflowType,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(\compact('message', 'timeoutWorkflowType') + ['type' => 'TimeoutFailure']),
            $message,
            $previous
        );

        $this->lastHeartbeatDetails = $lastHeartbeatDetails;
        $this->timeoutType = $timeoutWorkflowType;
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
