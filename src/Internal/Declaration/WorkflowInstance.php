<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance\QueryDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\SignalDispatcher;
use Temporal\Internal\Declaration\WorkflowInstance\UpdateDispatcher;
use Temporal\Internal\Interceptor;

/**
 * @internal
 */
final class WorkflowInstance extends Instance implements WorkflowInstanceInterface
{
    private readonly QueryDispatcher $queryDispatcher;
    private readonly SignalDispatcher $signalDispatcher;
    private UpdateDispatcher $updateDispatcher;

    /**
     * @param object $context Workflow object
     * @param Interceptor\Pipeline<WorkflowInboundCallsInterceptor, mixed> $pipeline
     */
    public function __construct(
        private readonly WorkflowPrototype $prototype,
        object $context,
        Interceptor\Pipeline $pipeline,
    ) {
        parent::__construct($prototype, $context);

        $this->queryDispatcher = new QueryDispatcher($pipeline, $context);
        $this->signalDispatcher = new SignalDispatcher($pipeline, $context);
        $this->updateDispatcher = new UpdateDispatcher($pipeline, $context);

        foreach ($prototype->getQueryHandlers() as $definition) {
            $this->queryDispatcher->addFromQueryDefinition($definition);
        }

        foreach ($prototype->getSignalHandlers() as $definition) {
            $this->signalDispatcher->addFromSignalDefinition($definition);
        }

        foreach ($prototype->getUpdateHandlers() as $definition) {
            $this->updateDispatcher->addFromUpdateDefinition($definition);
        }
    }

    public function getQueryDispatcher(): QueryDispatcher
    {
        return $this->queryDispatcher;
    }

    public function getSignalDispatcher(): SignalDispatcher
    {
        return $this->signalDispatcher;
    }

    public function getUpdateDispatcher(): UpdateDispatcher
    {
        return $this->updateDispatcher;
    }

    /**
     * Trigger constructor in Process context.
     */
    public function init(array $arguments = []): void
    {
        if (!\method_exists($this->context, '__construct')) {
            return;
        }

        $this->context->__construct(...$arguments);
    }

    public function getPrototype(): WorkflowPrototype
    {
        return $this->prototype;
    }
}
