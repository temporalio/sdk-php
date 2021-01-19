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
use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\RequestInterface;

final class StackTrace extends WorkflowProcessAwareRoute
{
    /**
     * {@inheritDoc}
     * @throws \JsonException
     */
    public function handle(RequestInterface $request, array $headers, Deferred $resolver): void
    {
        $payload = $request->getOptions();
        $process = $this->findProcessOrFail($payload['runId'] ?? null);

        $context = $process->getContext();

        error_log($context->getLastTrace());

        $resolver->resolve(EncodedValues::fromValues([$context->getLastTrace()]));
    }
}
