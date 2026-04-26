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
     * @param array<string, string> $nexusHeaders Raw-string Nexus headers
     *        forwarded to the handler via the wire — distinct from `$header`
     *        (Temporal interceptor header, payload-typed values).
     */
    public function __construct(
        string $endpoint,
        string $service,
        string $operation,
        ValuesInterface $args,
        array $options,
        HeaderInterface $header,
        array $nexusHeaders = [],
    ) {
        parent::__construct(
            self::NAME,
            [
                'endpoint' => $endpoint,
                'service' => $service,
                'operation' => $operation,
                'options' => $options,
                // Force `{}` over `[]` on the wire — Go side decodes as map[string]string.
                'nexusHeaders' => $nexusHeaders === [] ? new \stdClass() : $nexusHeaders,
            ],
            $args,
            header: $header,
        );
    }
}
