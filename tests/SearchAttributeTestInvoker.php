<?php

declare(strict_types=1);

namespace Temporal\Tests;

use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Client\GRPC\OperatorClient;

final class SearchAttributeTestInvoker
{
    public function __invoke(): void
    {
        $result = OperatorClient::create(getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233')->AddSearchAttributes(
            new AddSearchAttributesRequest(
                [
                    'search_attributes' => [
                        'attr1' => 2, // Keyword
                        'attr2' => 5, // Bool
                    ],
                ],
            ),
        );

        $result->getMetadata();
    }
}
