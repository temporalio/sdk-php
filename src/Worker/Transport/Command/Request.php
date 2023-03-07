<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\EncodedHeader;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\HeaderInterface;
use Temporal\DataConverter\ValuesInterface;

/**
 * Carries request to perform host action with payloads and failure as context. Can be cancelled if allows
 *
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
class Request extends Command implements RequestInterface
{
    protected string $name;
    protected array $options;
    protected ValuesInterface $payloads;
    protected ?HeaderInterface $header = null;
    protected ?\Throwable $failure = null;

    /**
     * @param string $name
     * @param RequestOptions $options
     * @param ValuesInterface|null $payloads
     * @param int|null $id
     */
    public function __construct(
        string $name,
        array $options = [],
        ValuesInterface $payloads = null,
        int $id = null,
        ?HeaderInterface $header = null,
    ) {
        $this->name = $name;
        $this->options = $options;
        $this->payloads = $payloads ?? EncodedValues::empty();
        $this->header = $header ?? EncodedHeader::empty();

        parent::__construct($id);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return RequestOptions
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return ValuesInterface
     */
    public function getPayloads(): ValuesInterface
    {
        return $this->payloads;
    }

    /**
     * @param \Throwable|null $failure
     */
    public function setFailure(?\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @return \Throwable|null
     */
    public function getFailure(): ?\Throwable
    {
        return $this->failure;
    }

    /**
     * @return EncodedHeader
     */
    public function getHeader(): EncodedHeader
    {
        return $this->header;
    }

    public function withHeader(HeaderInterface $header): self
    {
        $clone = clone $this;
        $clone->header = $header;
        return $clone;
    }
}
