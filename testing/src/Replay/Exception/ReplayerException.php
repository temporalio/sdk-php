<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay\Exception;

class ReplayerException extends \Exception
{
    public function __construct(
        protected string $workflowType,
        string $message,
        int $code,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }
}
