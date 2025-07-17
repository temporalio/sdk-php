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
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Request\UndefinedResponse;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

class CancelWorkflow extends WorkflowProcessAwareRoute
{
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to cancel workflow because workflow process #%s was not found';

    public function __construct(
        private readonly ClientInterface $client,
        protected RepositoryInterface $running,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $this->cancel($request->getID());

        $resolver->resolve(EncodedValues::fromValues([null]));
    }

    private function cancel(string $runId): void
    {
        $process = $this->running->find($runId);

        if ($process === null) {
            $this->client->send(new UndefinedResponse(
                \sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId),
            ));
            return;
        }

        $process->cancel();
    }
}
