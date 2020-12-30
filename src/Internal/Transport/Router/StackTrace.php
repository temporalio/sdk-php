<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;

final class StackTrace extends WorkflowProcessAwareRoute
{
    /**
     * {@inheritDoc}
     * @throws \JsonException
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $process = $this->findProcessOrFail($payload['runId'] ?? null);

        $context = $process->getContext();

        $resolver->resolve(
            $this->traceToJson(
                $context->getTrace()
            )
        );
    }

    /**
     * @param array $backtrace
     * @return string
     * @throws \JsonException
     */
    private function traceToJson(array $backtrace): string
    {
        return \json_encode($backtrace, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
    }
}
