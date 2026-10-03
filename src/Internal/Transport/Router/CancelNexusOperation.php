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
use Temporal\Api\Nexus\V1\CancelOperationRequest;
use Temporal\Api\Nexus\V1\Request;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Cancel routines are not interruptible; no canceller is registered for them.
 */
final class CancelNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
        private readonly MarshallerInterface $marshaller,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        try {
            $options = $request->getOptions();
            $operationContext = $this->marshaller->unmarshal($options, new NexusOperationContext());
            $protoRequest = self::buildProtoRequest($options);
            $this->taskHandler->handleCancelOperation(
                $protoRequest,
                $operationContext,
            );
            $resolver->resolve(EncodedValues::fromValues([]));
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildProtoRequest(array $options): Request
    {
        $cancelRequest = (new CancelOperationRequest())
            ->setService((string) ($options['service'] ?? ''))
            ->setOperation((string) ($options['operation'] ?? ''))
            ->setOperationToken((string) ($options['operationToken'] ?? ''));

        return (new Request())
            ->setHeader((array) ($options['headers'] ?? []))
            ->setCancelOperation($cancelRequest);
    }
}
