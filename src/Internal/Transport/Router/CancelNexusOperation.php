<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Route for "CancelNexusOperation" from RR.
 * Options: {service, operation, operationToken}.
 */
final class CancelNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();

        $service = $options['service'] ?? '';
        $operation = $options['operation'] ?? '';
        $operationToken = $options['operationToken'] ?? '';

        $context = new OperationContext(
            service: $service,
            operation: $operation,
        );

        $details = new OperationCancelDetails(operationToken: $operationToken);

        try {
            $this->taskHandler->cancelOperationDirect($context, $details);
            $resolver->resolve(EncodedValues::fromValues([]));
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }
}
