<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\ExternalStorage;

use Google\Protobuf\Internal\Message;
use Psr\Log\LoggerInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\DataConverter\ExternalStorage\StorageDriverActivityInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverClaim;
use Temporal\DataConverter\ExternalStorage\StorageDriverInterface;
use Temporal\DataConverter\ExternalStorage\StorageDriverRetrieveContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverSelectContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverStoreContext;
use Temporal\DataConverter\ExternalStorage\StorageDriverTargetInfo;
use Temporal\DataConverter\ExternalStorage\StorageDriverWorkflowInfo;
use Temporal\Exception\DataConverterException;

/**
 * @internal
 */
final class ExternalStorageRunner
{
    private readonly StorageBudget $sharedBudget;

    public function __construct(
        private readonly ExternalStorage $options,
        private readonly string $namespace,
        private readonly LoggerInterface $logger,
    ) {
        $this->sharedBudget = new StorageBudget($options->maxDriverOperations);
    }

    public function store(object $request): object
    {
        if (!$request instanceof Message || !$this->hasLargePayload($request)) {
            return $request;
        }

        $copy = clone $request;
        $copy->clear();
        $copy->mergeFromString($request->serializeToString());

        $target = self::target($copy);
        $payloads = PayloadWalker::collect($copy);
        $messageBudget = new StorageBudget($this->options->maxOperationsPerMessage);
        $selectContext = new StorageDriverSelectContext($target, $this->namespace);
        foreach ($this->selectDrivers($selectContext, $payloads) as $name => $indexes) {
            $driver = $this->options->drivers[$name];
            $limiter = new StorageLimiter($messageBudget, $this->sharedBudget);
            $batch = \array_map(static fn(int $index): Payload => $payloads[$index], $indexes);
            $claims = $driver->store(new StorageDriverStoreContext($target, $this->namespace, $limiter), $batch);
            self::assertBatch($name, $claims, \count($batch), StorageDriverClaim::class, 'claims', 'payloads');
            $this->warnWithoutPermit($driver, $limiter);

            foreach ($indexes as $position => $index) {
                $original = $payloads[$index];
                self::replace(
                    $original,
                    ReferencePayload::create($name, $claims[$position], \strlen($original->serializeToString())),
                );
            }
        }

        return $copy;
    }

    public function retrieve(Payload $payload): Payload
    {
        $reference = ReferencePayload::parse($payload);
        if ($reference === null) {
            return $payload;
        }

        $name = $reference->getDriverName();
        $driver = $this->options->drivers[$name] ?? null;
        if ($driver === null) {
            throw new DataConverterException("No storage driver registered with name '$name'.");
        }

        /** @var array<string, string> $claimData */
        $claimData = \iterator_to_array($reference->getClaimData());
        $limiter = new StorageLimiter(new StorageBudget($this->options->maxOperationsPerMessage), $this->sharedBudget);
        $retrieved = $driver->retrieve(new StorageDriverRetrieveContext($limiter), [new StorageDriverClaim($claimData)]);
        self::assertBatch($name, $retrieved, 1, Payload::class, 'payloads', 'claims');
        $this->warnWithoutPermit($driver, $limiter);

        return $retrieved[0];
    }

    private static function replace(Payload $target, Payload $source): void
    {
        $target->setMetadata(\iterator_to_array($source->getMetadata()));
        $target->setData($source->getData());
        $target->setExternalPayloads(\iterator_to_array($source->getExternalPayloads()));
    }

    /**
     * @param class-string $class
     */
    private static function assertBatch(
        string $driverName,
        mixed $result,
        int $expected,
        string $class,
        string $resultKind,
        string $inputKind,
    ): void {
        if (!\is_array($result) || \count($result) !== $expected || !\array_is_list($result)) {
            throw new DataConverterException(\sprintf(
                "Storage driver '%s' returned %d %s for %d %s.",
                $driverName,
                \is_array($result) ? \count($result) : 0,
                $resultKind,
                $expected,
                $inputKind,
            ));
        }

        foreach ($result as $position => $item) {
            if (!$item instanceof $class) {
                throw new DataConverterException(\sprintf(
                    "Storage driver '%s' returned an invalid element at position %d of the %s.",
                    $driverName,
                    $position,
                    $resultKind,
                ));
            }
        }
    }

    private static function target(Message $request): ?StorageDriverTargetInfo
    {
        switch (true) {
            case $request instanceof StartWorkflowExecutionRequest:
            case $request instanceof SignalWithStartWorkflowExecutionRequest:
                return new StorageDriverWorkflowInfo(
                    namespace: $request->getNamespace(),
                    workflowId: $request->getWorkflowId(),
                    workflowType: $request->getWorkflowType()?->getName() ?? '',
                );

            case $request instanceof ExecuteMultiOperationRequest:
                foreach ($request->getOperations() as $operation) {
                    $start = $operation->getStartWorkflow();
                    if ($start !== null) {
                        return self::target($start);
                    }
                }
                return null;

            case $request instanceof CreateScheduleRequest:
            case $request instanceof UpdateScheduleRequest:
                $start = $request->getSchedule()?->getAction()?->getStartWorkflow();
                return $start === null ? null : new StorageDriverWorkflowInfo(
                    namespace: $request->getNamespace(),
                    workflowId: $start->getWorkflowId(),
                    workflowType: $start->getWorkflowType()?->getName() ?? '',
                );

            case $request instanceof SignalWorkflowExecutionRequest:
            case $request instanceof UpdateWorkflowExecutionRequest:
            case $request instanceof TerminateWorkflowExecutionRequest:
                return self::execution($request->getNamespace(), $request->getWorkflowExecution());

            case $request instanceof QueryWorkflowRequest:
                return self::execution($request->getNamespace(), $request->getExecution());

            case $request instanceof StartActivityExecutionRequest:
                return new StorageDriverActivityInfo(
                    namespace: $request->getNamespace(),
                    activityId: $request->getActivityId(),
                    activityType: $request->getActivityType()?->getName() ?? '',
                );

            case $request instanceof RespondActivityTaskCompletedByIdRequest:
            case $request instanceof RespondActivityTaskFailedByIdRequest:
            case $request instanceof RespondActivityTaskCanceledByIdRequest:
            case $request instanceof RecordActivityTaskHeartbeatByIdRequest:
                if ($request->getWorkflowId() === '') {
                    return new StorageDriverActivityInfo(
                        namespace: $request->getNamespace(),
                        activityId: $request->getActivityId(),
                        runId: $request->getRunId(),
                    );
                }

                return new StorageDriverWorkflowInfo(
                    namespace: $request->getNamespace(),
                    workflowId: $request->getWorkflowId(),
                    runId: $request->getRunId(),
                );
        }

        return null;
    }

    private static function execution(string $namespace, ?WorkflowExecution $execution): StorageDriverWorkflowInfo
    {
        return new StorageDriverWorkflowInfo(
            namespace: $namespace,
            workflowId: $execution?->getWorkflowId() ?? '',
            runId: $execution?->getRunId() ?? '',
        );
    }

    private function hasLargePayload(Message $message): bool
    {
        foreach (PayloadWalker::collect($message) as $payload) {
            if ($this->exceedsThreshold($payload)) {
                return true;
            }
        }

        return false;
    }

    private function exceedsThreshold(Payload $payload): bool
    {
        if (ReferencePayload::isReference($payload)) {
            return false;
        }

        $threshold = $this->options->payloadSizeThreshold;

        return $threshold === 0 || \strlen($payload->serializeToString()) >= $threshold;
    }

    /**
     * @param list<Payload> $payloads
     * @return array<string, non-empty-list<int>>
     */
    private function selectDrivers(StorageDriverSelectContext $context, array $payloads): array
    {
        $batches = [];
        foreach ($payloads as $index => $payload) {
            if (!$this->exceedsThreshold($payload)) {
                continue;
            }

            $driver = ($this->options->driverSelector)($context, $payload);
            if ($driver === null) {
                continue;
            }

            $name = $driver->getName();
            if (($this->options->drivers[$name] ?? null) !== $driver) {
                throw new DataConverterException(
                    "Storage driver selector returned a driver not registered with this external storage: '$name'.",
                );
            }

            $batches[$name][] = $index;
        }

        return $batches;
    }

    private function warnWithoutPermit(StorageDriverInterface $driver, StorageLimiter $limiter): void
    {
        if (!$limiter->wasUsed()) {
            $this->logger->warning(\sprintf(
                "Storage driver '%s' completed an operation without taking a permit from the limiter. Its requests are unbounded.",
                $driver->getName(),
            ));
        }
    }
}
