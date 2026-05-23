<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

/**
 * Central helper for Fiber-based workflow execution.
 *
 * In Fiber mode, suspends the current Fiber with a PromiseInterface.
 * The Scope will resume the Fiber when the promise resolves.
 *
 * @experimental
 * @internal
 */
final class FiberHelper
{
    /**
     * Suspends the current Fiber with the given promise and returns the
     * resolved value when the Scope resumes it.
     *
     * @throws OutOfContextException when called outside a Fiber-mode workflow scope.
     */
    public static function await(PromiseInterface $promise): mixed
    {
        if (!self::isInFiberMode()) {
            throw new OutOfContextException(
                'FiberHelper::await() can be used only inside a Fiber-mode workflow scope.',
            );
        }

        return \Fiber::suspend($promise);
    }

    /**
     * @internal Sibling Fiber primitives may consume this to gate their own
     *           passthrough behavior. Not part of the public API.
     */
    public static function isInFiberMode(): bool
    {
        $context = Facade::getCurrentContext();

        return $context instanceof ScopeContext && $context->isFiberMode();
    }
}
