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
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Internal\Exception\UndefinedRequestException;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

class CancelWorkflow extends WorkflowProcessAwareRoute
{
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to cancel workflow because workflow process #%s was not found';

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $this->cancel($request->getID(), self::cancelReason($request));

        $resolver->resolve(EncodedValues::fromValues([null]));
    }

    private static function cancelReason(ServerRequestInterface $request): ?CanceledFailure
    {
        $cause = $request->getOptions()['cause'] ?? '';

        return $cause === '' ? null : new CanceledFailure($cause);
    }

    /**
     * @throws UndefinedRequestException
     */
    private function cancel(string $runId, ?CanceledFailure $reason): void
    {
        $process = $this->running->find($runId) ?? throw new UndefinedRequestException(
            \sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId),
        );

        $process->cancel($reason);
    }
}
