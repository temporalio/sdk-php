<?php


namespace Temporal\Exception\Failure;


use Temporal\Api\Enums\V1\TimeoutType;
use Temporal\DataConverter\EncodedValues;
use Throwable;

class TimeoutFailure extends TemporalFailure
{
    /**
     * @var EncodedValues
     */
    private ?EncodedValues $lastHeartbeatDetails;

    /**
     * @var int
     */
    private int $timeoutType;

    /**
     * @param string $message
     * @param EncodedValues|null $lastHeartbeatDetails
     * @param int $timeoutType
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        ?EncodedValues $lastHeartbeatDetails,
        int $timeoutType,
        Throwable $previous = null
    ) {
        parent::__construct(self::buildMessage($message, $timeoutType), $message, $previous);
        $this->lastHeartbeatDetails = $lastHeartbeatDetails;
        $this->timeoutType = $timeoutType;
    }

    /**
     * @return EncodedValues|null
     */
    public function getLastHeartbeatDetails(): ?EncodedValues
    {
        return $this->lastHeartbeatDetails;
    }

    /**
     * @return int
     */
    public function getTimeoutType(): int
    {
        return $this->timeoutType;
    }

    /**
     * @param string $message
     * @param int $timeoutType
     * @return string
     */
    public static function buildMessage(string $message, int $timeoutType): string
    {
        if ($message !== '') {
            return 'message=' . $message . ',timeoutType=' . TimeoutType::name($timeoutType);
        }

        return 'timeoutType=' . TimeoutType::name($timeoutType);
    }
}
