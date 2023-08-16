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

/**
 * Carries request to perform host action with payloads and failure as context. Can be cancelled if allows
 *
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
class ServerRequest implements ServerRequestInterface
{
    use RequestTrait;

    private string $id;
    protected ValuesInterface $payloads;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $id
     * @param RequestOptions $options
     * @param object|null $header
     * @param int<0, max> $historyLength
     */
    public function __construct(
        private string $name,
        private array $options = [],
        ?ValuesInterface $payloads = null,
        ?string $id = null,
        ?object $header = null,
        private int $historyLength = 0,
        private ?string $runId = null,
    ) {
        $this->payloads = $payloads ?? EncodedValues::empty();
        $this->id = $id ?? $this->options['info']['WorkflowExecution']['RunID'] ?? $this->options['runId'] ?? '';
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
