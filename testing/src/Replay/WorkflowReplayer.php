<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Coresdk\Workflow_activation\RemoveFromCache\EvictionReason;
use Google\Protobuf\Descriptor;
use Google\Protobuf\DescriptorPool;
use Google\Protobuf\EnumDescriptor;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\Message;
use RoadRunner\Temporal\DTO\V1\ReplayRequest;
use RoadRunner\Temporal\DTO\V1\ReplayResponse;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\History\V1\History;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Testing\Replay\Exception\InternalServerException;
use Temporal\Testing\Replay\Exception\InvalidArgumentException;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\Exception\RPCException;
use Temporal\Worker\Core\CoreWorkerFactory;

/**
 * Replays a workflow given its history. Useful for backwards compatibility testing.
 *
 * @link https://docs.temporal.io/dev-guide/php/testing#replay
 * @since RoadRunner 2023.3
 */
final class WorkflowReplayer
{
    private const REPLAY_WORKFLOW_ID = 'replay';

    private RPC $rpc;

    public function __construct(
        private readonly ?CoreWorkerFactory $factory = null,
        private readonly ?WorkflowClientInterface $client = null,
    ) {
        $rpcAddress = Environment::fromGlobals()->getRPCAddress();
        $this->rpc = new RPC(Relay::create(!empty($rpcAddress) ? $rpcAddress : 'tcp://127.0.0.1:6001'), new ProtobufCodec());
    }

    /**
     * Replays a workflow from {@see History}
     *
     * @throws ReplayerException
     */
    public function replayHistory(History $history): void
    {
        $firstEvent = $history->getEvents()[0] ?? null;
        $workflowType = $firstEvent?->getWorkflowExecutionStartedEventAttributes()?->getWorkflowType()?->getName()
            ?? throw new \LogicException('History is empty or broken.');

        if ($this->factory !== null) {
            $this->replayOnCore($this->factory, $workflowType, $history, self::REPLAY_WORKFLOW_ID);
            return;
        }

        $request = (new \RoadRunner\Temporal\DTO\V1\History())
            ->setWorkflowType((new WorkflowType())->setName($workflowType))
            ->setHistory($history);

        $this->sendRequest('temporal.ReplayWorkflowHistory', $request);
    }

    /**
     * Replays a workflow from history that will be fetched from Temporal server.
     *
     * @throws ReplayerException
     */
    public function replayFromServer(
        string $workflowType,
        \Temporal\Workflow\WorkflowExecution $execution,
    ): void {
        if ($this->factory !== null) {
            $this->replayOnCore($this->factory, $workflowType, $this->fetchHistory($execution), $execution->getID());
            return;
        }

        $request = $this->buildRequest($workflowType, $execution);
        $this->sendRequest('temporal.ReplayWorkflow', $request);
    }

    /**
     * Downloads workflow history from Temporal server and saves it to a file.
     *
     * @param non-empty-string $workflowType
     * @param non-empty-string $savePath
     *
     * @throws ReplayerException
     */
    public function downloadHistory(
        string $workflowType,
        \Temporal\Workflow\WorkflowExecution $execution,
        string $savePath,
    ): void {
        if ($this->factory !== null) {
            \file_put_contents($savePath, $this->fetchHistory($execution)->serializeToJsonString());
            return;
        }

        $request = $this->buildRequest($workflowType, $execution, $savePath);
        $this->sendRequest('temporal.DownloadWorkflowHistory', $request);
    }

    /**
     * Replays workflow from a json serialized history file.
     * You can load a json serialized history file using {@see downloadHistory()} or via Temporal UI.
     *
     * @param non-empty-string $workflowType
     * @param non-empty-string|\SplFileInfo $path
     * @param int<0, max> $lastEventId The last event ID to replay from. If not specified, the whole history
     *        will be replayed.
     *
     * @throws ReplayerException
     */
    public function replayFromJSON(
        string $workflowType,
        string|\SplFileInfo $path,
        int $lastEventId = 0,
    ): void {
        if ($this->factory !== null) {
            $this->replayOnCore($this->factory, $workflowType, $this->historyFromJson($workflowType, (string) $path, $lastEventId), self::REPLAY_WORKFLOW_ID);
            return;
        }

        $request = $this->buildRequest(
            workflowType: $workflowType,
            filePath: $path instanceof \SplFileInfo ? $path->getPathname() : $path,
            lastEventId: $lastEventId,
        );
        $this->sendRequest('temporal.ReplayFromJSON', $request);
    }

    private static function normalizeEnums(\stdClass $message, Descriptor $descriptor): void
    {
        for ($i = 0; $i < $descriptor->getFieldCount(); ++$i) {
            $field = $descriptor->getField($i);
            $key = \lcfirst(\str_replace('_', '', \ucwords($field->getName(), '_')));
            if (!isset($message->{$key}) || $field->isMap()) {
                continue;
            }

            $values = $field->isRepeated() ? $message->{$key} : [$message->{$key}];
            if ($field->getType() === GPBType::MESSAGE) {
                foreach ($values as $value) {
                    if ($value instanceof \stdClass) {
                        self::normalizeEnums($value, $field->getMessageType());
                    }
                }
            }

            if ($field->getType() === GPBType::ENUM) {
                $names = \array_map(static fn(mixed $value): mixed => \is_string($value) ? self::enumName($field->getEnumType(), $value) : $value, $values);
                $message->{$key} = $field->isRepeated() ? $names : $names[0];
            }
        }
    }

    private static function enumName(EnumDescriptor $enum, string $value): string
    {
        $prefix = \substr($enum->getValue(0)->getName(), 0, -\strlen('UNSPECIFIED'));
        if (\str_starts_with($value, $prefix)) {
            return $value;
        }

        return $prefix . \strtoupper((string) \preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value));
    }

    private function replayOnCore(CoreWorkerFactory $factory, string $workflowType, History $history, string $workflowId): void
    {
        $failure = $factory->replay($history, $workflowId);
        if ($failure === null) {
            return;
        }

        if ($failure->getReason() === EvictionReason::NONDETERMINISM) {
            throw new NonDeterministicWorkflowException($workflowType, $failure->getMessage(), StatusCode::FAILED_PRECONDITION);
        }

        throw new ReplayerException($workflowType, $failure->getMessage(), StatusCode::INTERNAL);
    }

    private function fetchHistory(\Temporal\Workflow\WorkflowExecution $execution): History
    {
        $client = $this->client ?? throw new \LogicException('A workflow client is required to fetch a history on the sdk-core transport.');

        return $client->getWorkflowHistory($execution)->getHistory();
    }

    private function historyFromJson(string $workflowType, string $path, int $lastEventId): History
    {
        if (!\is_file($path)) {
            throw new InvalidArgumentException($workflowType, \sprintf('History file "%s" does not exist.', $path), StatusCode::INVALID_ARGUMENT);
        }

        $history = new History();
        $json = \json_decode((string) \file_get_contents($path), flags: \JSON_THROW_ON_ERROR);
        self::normalizeEnums($json, DescriptorPool::getGeneratedPool()->getDescriptorByClassName(History::class));
        $history->mergeFromJsonString(\json_encode($json, \JSON_THROW_ON_ERROR), true);

        $events = [];
        foreach ($history->getEvents() as $event) {
            $events[] = $event;
            if ($event->getEventId() === $lastEventId) {
                break;
            }
        }
        $history->setEvents($events);

        return $history;
    }

    /**
     * @param non-empty-string $command
     */
    private function sendRequest(string $command, Message $request): void
    {
        $wfType = (string) $request->getWorkflowType()?->getName();
        try {
            /** @var string $result */
            $result = $this->rpc->call($command, $request);
        } catch (\Throwable $e) {
            throw new RPCException(
                $wfType,
                $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }

        $message = new ReplayResponse();
        $message->mergeFromString($result);

        $status = $message->getStatus();
        \assert($status !== null);

        if ($status->getCode() === 0) {
            return;
        }

        throw match ($status->getCode()) {
            StatusCode::INVALID_ARGUMENT => new InvalidArgumentException(
                $wfType,
                $status->getMessage(),
                $status->getCode(),
            ),
            StatusCode::INTERNAL => new InternalServerException($wfType, $status->getMessage(), $status->getCode()),
            StatusCode::FAILED_PRECONDITION => new NonDeterministicWorkflowException(
                $wfType,
                $status->getMessage(),
                $status->getCode(),
            ),
            default => new ReplayerException($wfType, $status->getMessage(), $status->getCode()),
        };
    }

    private function buildRequest(
        string $workflowType,
        ?\Temporal\Workflow\WorkflowExecution $execution = null,
        ?string $filePath = null,
        int $lastEventId = 0,
    ): ReplayRequest {
        $request = (new ReplayRequest())
            ->setWorkflowType((new WorkflowType())->setName($workflowType))
            ->setLastEventId($lastEventId);

        if ($execution !== null) {
            $request->setWorkflowExecution(
                (new WorkflowExecution())
                    ->setWorkflowId($execution->getID())
                    ->setRunId($execution->getRunID() ?? throw new \LogicException('Run ID is required.')),
            );
        }

        if ($filePath !== null) {
            $request->setSavePath($filePath);
        }


        return $request;
    }
}
