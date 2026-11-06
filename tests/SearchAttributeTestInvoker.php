<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Testing\TemporalServer;

final class SearchAttributeTestInvoker
{
    public function __invoke(): void
    {
        try {
            OperatorClient::create(TemporalServer::address())->AddSearchAttributes(
                new AddSearchAttributesRequest(
                    [
                        'search_attributes' => [
                            'attr1' => 2, // Keyword
                            'attr2' => 5, // Bool
                        ]
                    ]
                )
            );
        } catch (ServiceClientException) {
        }
    }
}
