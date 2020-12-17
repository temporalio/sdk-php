<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport;

use React\Promise\PromiseInterface;
use Temporal\Client\Worker\Command\RequestInterface;

interface ClientInterface
{
    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface;
}
