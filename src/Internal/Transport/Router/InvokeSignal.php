<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class InvokeSignal extends WorkflowProcessAwareRoute
{
    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $instance = $this->findInstanceOrFail($request->getID());
        $handler = $instance->getSignalHandler($request->getOptions()['name']);

        // Get Workflow context
        $context = $this->findProcessOrFail($request->getID())->getContext();
        /** @psalm-suppress InaccessibleProperty */
        $context->getInfo()->historyLength = $request->getHistoryLength();

        $handler($request->getPayloads());

        $resolver->resolve(EncodedValues::fromValues([null]));
    }
}
