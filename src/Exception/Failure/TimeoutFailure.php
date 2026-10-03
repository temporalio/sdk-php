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
use Temporal\DataConverter\SerializationContext;
use Temporal\DataConverter\ValuesInterface;

class TimeoutFailure extends TemporalFailure
{
    private ValuesInterface $lastHeartbeatDetails;
    private int $timeoutType;

    public function __construct(
        string $message,
        ValuesInterface $lastHeartbeatDetails,
        int $timeoutWorkflowType,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::buildMessage(\compact('message', 'timeoutWorkflowType') + ['type' => 'TimeoutFailure']),
            $message,
            $previous,
        );

        $this->lastHeartbeatDetails = $lastHeartbeatDetails;
        $this->timeoutType = $timeoutWorkflowType;
    }

    public function getTimeoutType(): int
    {
        return $this->timeoutType;
    }

    public function getLastHeartbeatDetails(): ValuesInterface
    {
        return $this->lastHeartbeatDetails;
    }

    public function setDataConverter(DataConverterInterface $converter): void
    {
        parent::setDataConverter($converter);
        $this->lastHeartbeatDetails->setDataConverter($converter);
        $this->lastHeartbeatDetails->setSerializationContext($this->getSerializationContext());
    }

    public function setSerializationContext(?SerializationContext $context): void
    {
        parent::setSerializationContext($context);
        $this->lastHeartbeatDetails->setSerializationContext($context);
    }
}
