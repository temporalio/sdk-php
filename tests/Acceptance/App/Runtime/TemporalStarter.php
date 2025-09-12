<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Runtime;

use Symfony\Component\Process\Process;
use Temporal\Common\SearchAttributes\ValueType;
use Temporal\Testing\Environment;

final class TemporalStarter
{
    private Environment $environment;
    private bool $started = false;

    public function __construct()
    {
        $this->environment = Environment::create();
        \register_shutdown_function(fn() => $this->stop());
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->environment->startTemporalServer(
            parameters: [
                '--dynamic-config-value', 'frontend.enableUpdateWorkflowExecution=true',
                '--dynamic-config-value', 'frontend.enableUpdateWorkflowExecutionAsyncAccepted=true',
                '--dynamic-config-value', 'frontend.enableExecuteMultiOperation=true',
                '--dynamic-config-value', 'system.enableEagerWorkflowStart=true',
                '--dynamic-config-value', 'frontend.activityAPIsEnabled=true',
                '--dynamic-config-value', 'frontend.workerVersioningWorkflowAPIs=true',
                '--dynamic-config-value', 'system.enableDeploymentVersions=true',
            ],
            searchAttributes: [
                'foo' => ValueType::Text->value,
                'bar' => ValueType::Int->value,
                'testBool' => ValueType::Bool,
                'testInt' => ValueType::Int,
                'testFloat' => ValueType::Float,
                'testText' => ValueType::Text,
                'testKeyword' => ValueType::Keyword,
                'testKeywordList' => ValueType::KeywordList,
                'testDatetime' => ValueType::Datetime,
            ],
        );
        $this->started = true;
    }

    public function executeTemporalCommand(array|string $command, int $timeout = 10): void
    {
        $this->environment->executeTemporalCommand(
            command: $command,
            timeout: $timeout,
        );
    }

    /**
     * @return bool Returns true if the server was stopped successfully, false if it was not started.
     */
    public function stop(): bool
    {
        if (!$this->started) {
            return false;
        }

        $this->environment->stop();
        $this->started = false;
        return true;
    }
}
