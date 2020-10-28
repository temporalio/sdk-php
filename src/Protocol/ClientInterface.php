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
use Temporal\Client\Protocol\Command\RequestInterface;

interface ClientInterface
{
    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface;

    /**
     * @param int $id
     * @return bool
     */
    public function cancel(int $id): bool;
}
