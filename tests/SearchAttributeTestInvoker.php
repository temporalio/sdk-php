<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Exception\Client\ServiceClientException;

final class SearchAttributeTestInvoker
{
    public function __invoke(): void
    {
        $namespace = getenv('TEMPORAL_NAMESPACE') ?: 'default';

        try {
            OperatorClient::create(getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233')->AddSearchAttributes(
                new AddSearchAttributesRequest(
                    [
                        'search_attributes' => [
                            'attr1' => 2, // Keyword
                            'attr2' => 5, // Bool
                        ],
                        'namespace' => $namespace,
                    ],
                ),
            );
        } catch (ServiceClientException $e) {
            if ($e->getCode() !== StatusCode::ALREADY_EXISTS) {
                throw $e;
            }

            echo "Search attributes already registered, skipping.\n";
        }
    }
}
