<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

use Temporal\Client\Transport\Message;

class Request extends Message implements RequestInterface
{
    /**
     * @var string
     */
    private string $method;

    /**
     * @param string $method
     * @param $payload
     */
    public function __construct(string $method, $payload)
    {
        $this->method = $method;

        parent::__construct($payload);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->method;
    }
}
