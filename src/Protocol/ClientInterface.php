<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Message\RequestInterface;

interface ClientInterface extends ProtocolInterface
{
    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface;

    /**
     * @param RequestInterface $request
     * @param RequestInterface ...$requests
     * @return PromiseInterface[]
     */
    public function batch(RequestInterface $request, RequestInterface ...$requests): iterable;
}
