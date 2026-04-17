<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * Handles "CancelNexusOperation" command from Go RoadRunner plugin.
 *
 * Go sends: options={service, operation, operationToken}
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
            // NexusHandlerErrorException and plain throwables route through
            // the same rejection path — the RR transport inspects the type.
            $resolver->reject($e);
        }
    }
}
