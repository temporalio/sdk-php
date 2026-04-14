<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Nexus\NexusHandlerErrorException;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Api\Nexus\V1\Request;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

final class CancelNexusOperation extends Route
{
    public function __construct(
        private readonly NexusTaskHandler $taskHandler,
    ) {}

    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $options = $request->getOptions();

        $nexusRequest = new Request();
        $nexusRequest->mergeFromString(\base64_decode($options['request'] ?? ''));

        try {
            $response = $this->taskHandler->handleCancelOperation($nexusRequest);
            $resolver->resolve(EncodedValues::fromValues([\base64_encode($response->serializeToString())]));
        } catch (NexusHandlerErrorException $e) {
            $resolver->reject($e);
        } catch (\Throwable $e) {
            $resolver->reject($e);
        }
    }
}
