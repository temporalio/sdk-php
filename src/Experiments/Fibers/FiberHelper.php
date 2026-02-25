<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

/**
 * Central helper for Fiber-based workflow execution.
 *
 * In Fiber mode, suspends the current Fiber with a PromiseInterface.
 * The Scope will resume the Fiber when the promise resolves.
 *
 * In Generator mode, returns the PromiseInterface as-is for yielding.
 *
 * @experimental
 * @internal
 */
final class FiberHelper
{
    /**
     * If running inside a workflow Fiber, suspends and returns the resolved value.
     * Otherwise, returns the PromiseInterface for the caller to yield.
     */
    public static function await(PromiseInterface $promise): mixed
    {
        // Use Facade::getCurrentContext() which returns null outside workflow
        // (unlike Workflow::getCurrentContext() which throws)
        $context = Facade::getCurrentContext();

        if ($context instanceof ScopeContext && $context->isFiberMode()) {
            return \Fiber::suspend($promise);
        }

        return $promise;
    }
}
