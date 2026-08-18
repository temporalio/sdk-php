<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Temporal\Api\Operatorservice\V1\OperatorServiceClient;
use Grpc\ChannelCredentials;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Testing\TemporalServer;

final class SearchAttributeTestInvoker
{
    public function __invoke(): void
    {
        $operation = new OperatorServiceClient(
            TemporalServer::address(),
            ['credentials' => ChannelCredentials::createInsecure()]
        );
        $result = $operation->AddSearchAttributes(
            new AddSearchAttributesRequest(
                [
                    'search_attributes' => [
                        'attr1' => 2, // Keyword
                        'attr2' => 5, // Bool
                    ]
                ]
            )
        );

        $result->getMetadata();
    }
}
