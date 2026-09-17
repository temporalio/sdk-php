<?php

declare(strict_types=1);

namespace Temporal\Exception\Client\MultyOperation;

use Google\Protobuf\Any;
use Google\Protobuf\RepeatedField;
use Temporal\Api\Errordetails\V1\MultiOperationExecutionFailure\OperationStatus as OperationStatusMessage;
use Temporal\Exception\Client\UnpackDetailsTrait;

/**
 * @internal
 */
final class OperationStatus
{
    use UnpackDetailsTrait;

    /**
     * @param RepeatedField<Any> $details
     */
    private function __construct(
        private readonly \Traversable $details,
        private readonly string $message,
    ) {}

    public static function fromMessage(OperationStatusMessage $message): self
    {
        return new self($message->getDetails(), $message->getMessage());
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return RepeatedField<Any>
     */
    private function getDetails(): \Traversable
    {
        return $this->details;
    }
}
