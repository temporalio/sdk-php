<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\Api\Failure\V1\Failure;

class Request extends Command implements RequestInterface
{
    protected const CANCELLABLE = false;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array
     */
    protected array $params;

    /**
     * @var array
     */
    protected array $payloads;

    /**
     * @var \Throwable|null
     */
    protected ?\Throwable $failure = null;

    /**
     * @param string $name
     * @param array $params
     * @param array $payloads
     * @param int|null $id
     */
    public function __construct(
        string $name,
        array $params = [],
        array $payloads = [],
        int $id = null
    ) {
        $this->name = $name;
        $this->params = $params;
        $this->payloads = $payloads;

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
     * @return array
     */
    public function getOptions(): array
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function getPayloads(): array
    {
        return $this->payloads;
    }

    /**
     * @param \Throwable|null $e
     */
    public function setFailure(?\Throwable $e)
    {
        $this->failure = $e;
    }

    /**
     * @return \Throwable|null
     */
    public function getFailure(): ?\Throwable
    {
        return $this->failure;
    }

    /**
     * @return bool
     */
    public function isCancellable(): bool
    {
        return static::CANCELLABLE;
    }
}
