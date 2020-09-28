<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

class Request extends Message implements RequestInterface
{
    /**
     * @var string
     */
    private string $method;

    /**
     * @var array
     */
    protected array $params;

    /**
     * @param string $method
     * @param array $params
     * @param string|int|null $id
     */
    public function __construct(string $method, array $params = [], $id = null)
    {
        $this->method = $method;
        $this->params = $params;

        parent::__construct($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'method' => $this->getMethod(),
            // TODO "params" member MAY be omitted.
            'params' => $this->getParams(),
        ]);
    }
}
