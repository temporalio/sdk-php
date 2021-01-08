<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Command;

class Request extends Command implements RequestInterface
{
    protected const CANCELLABLE = false;
    protected const PAYLOAD_PARAMS = [];

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array
     */
    protected array $params;

    /**
     * @param string $name
     * @param array $params
     * @param int|null $id
     */
    public function __construct(string $name, array $params = [], int $id = null)
    {
        $this->name = $name;
        $this->params = $params;

        parent::__construct($id);
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isCancellable(): bool
    {
        return static::CANCELLABLE;
    }

    /**
     * @return array
     */
    public function getPayloadParams(): array
    {
        return static::PAYLOAD_PARAMS;
    }
}
