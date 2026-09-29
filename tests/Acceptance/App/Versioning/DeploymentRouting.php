<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Versioning;

use Temporal\Api\Enums\V1\RoutingConfigUpdateState;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentResponse;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentCurrentVersionRequest;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentRampingVersionRequest;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Workflow\WorkflowExecutionStatus;

final class DeploymentRouting
{
    private const TIMEOUT_SECONDS = 15;
    private const POLL_INTERVAL_MICROSECONDS = 300_000;

    public function __construct(
        private readonly WorkflowClientInterface $client,
        private readonly string $namespace,
        private readonly string $deploymentName,
    ) {}

    public function setCurrentVersion(string $buildId): void
    {
        $token = $this->awaitVersion($buildId);

        $this->client->getServiceClient()->SetWorkerDeploymentCurrentVersion(
            (new SetWorkerDeploymentCurrentVersionRequest())
                ->setNamespace($this->namespace)
                ->setDeploymentName($this->deploymentName)
                ->setBuildId($buildId)
                ->setConflictToken($token),
        );

        $this->awaitRoutingPropagation();
    }

    public function setRampingVersion(string $buildId, float $percentage): void
    {
        $token = $this->awaitVersion($buildId);

        $this->client->getServiceClient()->SetWorkerDeploymentRampingVersion(
            (new SetWorkerDeploymentRampingVersionRequest())
                ->setNamespace($this->namespace)
                ->setDeploymentName($this->deploymentName)
                ->setBuildId($buildId)
                ->setPercentage($percentage)
                ->setConflictToken($token),
        );

        $this->awaitRoutingPropagation();
    }

    public function awaitWorkflowRunning(string $workflowId): void
    {
        $this->await(
            fn(): bool => $this->client->newUntypedRunningWorkflowStub($workflowId)->describe()->info->status
                === WorkflowExecutionStatus::Running,
            "Workflow {$workflowId} did not start",
        );
    }

    private function awaitVersion(string $buildId): string
    {
        $token = null;
        $this->await(
            function () use ($buildId, &$token): bool {
                $response = $this->describe();
                if ($response === null) {
                    return false;
                }

                foreach ($response->getWorkerDeploymentInfo()?->getVersionSummaries() ?? [] as $summary) {
                    if ($summary->getDeploymentVersion()?->getBuildId() === $buildId) {
                        $token = $response->getConflictToken();
                        return true;
                    }
                }

                return false;
            },
            "Deployment {$this->deploymentName} did not report version {$buildId}",
        );

        return $token;
    }

    private function awaitRoutingPropagation(): void
    {
        $this->await(
            function (): bool {
                $state = $this->describe()?->getWorkerDeploymentInfo()?->getRoutingConfigUpdateState();

                return $state === RoutingConfigUpdateState::ROUTING_CONFIG_UPDATE_STATE_COMPLETED
                    || $state === RoutingConfigUpdateState::ROUTING_CONFIG_UPDATE_STATE_UNSPECIFIED;
            },
            "Deployment {$this->deploymentName} did not propagate its routing config",
        );
    }

    private function describe(): ?DescribeWorkerDeploymentResponse
    {
        try {
            return $this->client->getServiceClient()->DescribeWorkerDeployment(
                (new DescribeWorkerDeploymentRequest())
                    ->setNamespace($this->namespace)
                    ->setDeploymentName($this->deploymentName),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function await(callable $condition, string $message): void
    {
        $deadline = \microtime(true) + self::TIMEOUT_SECONDS;

        while (\microtime(true) < $deadline) {
            if ($condition()) {
                return;
            }

            \usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        throw new \RuntimeException($message);
    }
}
