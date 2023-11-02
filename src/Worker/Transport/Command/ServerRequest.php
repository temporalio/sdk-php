<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;

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
     * @param int<0, max> $historyLength
     */
    public function __construct(
        private string $name,
        private array $options = [],
        ?ValuesInterface $payloads = null,
        ?string $id = null,
        ?HeaderInterface $header = null,
        private int $historyLength = 0,
    ) {
        $this->payloads = $payloads ?? EncodedValues::empty();
        $this->header = $header ?? Header::empty();
        $this->id = $id ?? $options['info']['WorkflowExecution']['RunID'] ?? $options['runId'] ?? '';
    }

    public function getID(): string
    {
        return $this->id;
    }

    /**
     * @return int<0, max>
     */
    public function getHistoryLength(): int
    {
        return $this->historyLength;
    }
}
