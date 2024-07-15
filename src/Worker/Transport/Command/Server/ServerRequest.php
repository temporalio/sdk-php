<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Server;

use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\Common\RequestTrait;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

/**
 * A request from RoadRunner to the worker.
 *
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
class ServerRequest implements ServerRequestInterface
{
    use RequestTrait;

    private string $id;
    protected ValuesInterface $payloads;
    protected HeaderInterface $header;

    /**
     * @param non-empty-string $name
     * @param non-empty-string|null $id
     * @param RequestOptions $options
     */
    public function __construct(
        private string $name,
        private TickInfo $info,
        private array $options = [],
        ?ValuesInterface $payloads = null,
        ?string $id = null,
        ?HeaderInterface $header = null,
    ) {
        $this->payloads = $payloads ?? EncodedValues::empty();
        $this->header = $header ?? Header::empty();
        $this->id = $id ?? $options['info']['WorkflowExecution']['RunID'] ?? $options['runId'] ?? '';
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getTickInfo(): TickInfo
    {
        return $this->info;
    }
}
