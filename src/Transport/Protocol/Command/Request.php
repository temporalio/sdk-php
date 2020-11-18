<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Protocol\Command;

class Request extends Command implements RequestInterface
{
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
