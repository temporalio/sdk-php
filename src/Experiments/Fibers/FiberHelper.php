<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Workflow\ScopeContext;

/**
 * Suspends the current Fiber on a promise and resumes it with the resolved value.
 *
 * @experimental
 */
final class FiberHelper
{
    /**
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

    public static function isInFiberMode(): bool
    {
        $context = Facade::getCurrentContext();

        return $context instanceof ScopeContext && $context->isFiberMode();
    }
}
