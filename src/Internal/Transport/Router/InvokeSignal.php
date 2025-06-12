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
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $signalName = $request->getOptions()['name'] ?? '';
        $requestId = $request->getID();

        \assert(\is_string($signalName) && $signalName !== '');
        \assert($requestId !== '');

        $process = $this->findProcessOrFail($requestId);
        $handler = $process
            ->getContext()
            ->getSignalDispatcher()
            ->getSignalHandler($signalName);

        // Get Workflow context
        $context = $this->findProcessOrFail($requestId)->getContext();

        $info = $context->getInfo();
        $tickInfo = $request->getTickInfo();
        /** @psalm-suppress InaccessibleProperty */
        $info->historyLength = $tickInfo->historyLength;
        /** @psalm-suppress InaccessibleProperty */
        $info->historySize = $tickInfo->historySize;
        /** @psalm-suppress InaccessibleProperty */
        $info->shouldContinueAsNew = $tickInfo->continueAsNewSuggested;

        $handler($request->getPayloads());

        $resolver->resolve(EncodedValues::fromValues([null]));
    }
}
