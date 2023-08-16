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
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class StackTrace extends WorkflowProcessAwareRoute
{
    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $process = $this->findProcessOrFail($request->getID());

        $context = $process->getContext();

        $resolver->resolve(EncodedValues::fromValues([$context->getStackTrace()]));
    }
}
