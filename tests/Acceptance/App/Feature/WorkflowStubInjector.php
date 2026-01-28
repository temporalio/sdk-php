<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Feature;

use Temporal\Common\IdReusePolicy;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Container\InjectorInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;

/**
 * @implements InjectorInterface<WorkflowStubInterface>
 */
final class WorkflowStubInjector implements InjectorInterface
{
    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
        private readonly ClientFactory $clientFactory,
    ) {}

    public function createInjection(
        \ReflectionClass $class,
        \ReflectionParameter|null|string $context = null,
    ): WorkflowStubInterface {
        if (!$context instanceof \ReflectionParameter) {
            throw new \InvalidArgumentException('Context is not clear.');
        }

        /** @var Stub|null $attribute */
        $attribute = ($context->getAttributes(Stub::class)[0] ?? null)?->newInstance();
        $attribute ?? throw new \InvalidArgumentException(\sprintf('Attribute %s is not found.', Stub::class));

        $client = $this->clientFactory->workflowClient($context);

        /** @var Feature $feature */
        $feature = $this->container->get(Feature::class);
        $options = WorkflowOptions::new()
            ->withWorkflowExecutionTimeout($attribute->executionTimeout ?? '1 minute')
            ->withTaskQueue($feature->taskQueue)
            ->withRetryOptions($attribute->retryOptions)
            ->withEagerStart($attribute->eagerStart);

        if ($attribute->workflowId !== null) {
            $options = $options
                ->withWorkflowId($attribute->workflowId)
                ->withWorkflowIdReusePolicy(IdReusePolicy::AllowDuplicate);
        }
        if (!empty($attribute->memo)) {
            $options = $options->withMemo($attribute->memo);
        }

        $stub = $client->newUntypedWorkflowStub($attribute->type, $options);
        $run = $client->start($stub, ...$attribute->args);

        // Wait 5 seconds for the workflow to start
        $deadline = \microtime(true) + 5;
        checkStart:
        $description = $run->describe();
        if ($description->info->historyLength <= 2) {
            if (\microtime(true) < $deadline) {
                goto checkStart;
            }

            throw new \RuntimeException(
                \sprintf(
                    'Workflow %s did not start. TaskQueue: %s',
                    $attribute->type,
                    $feature->taskQueue,
                ),
            );
        }

        return $stub;
    }
}
