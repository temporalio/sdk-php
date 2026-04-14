<?php

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
final class ExecuteNexusOperation extends Request
{
    public const NAME = 'ExecuteNexusOperation';

    /**
     * @param non-empty-string $endpoint Nexus Endpoint name
     * @param non-empty-string $service Service name
     * @param non-empty-string $operation Operation name
     * @param RequestOptions $options
     */
    public function __construct(
        string $endpoint,
        string $service,
        string $operation,
        ValuesInterface $args,
        array $options,
        HeaderInterface $header,
    ) {
        parent::__construct(
            self::NAME,
            [
                'endpoint' => $endpoint,
                'service' => $service,
                'operation' => $operation,
                'options' => $options,
            ],
            $args,
            header: $header,
        );
    }
}
