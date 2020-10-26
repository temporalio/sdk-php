<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;

interface DispatcherInterface
{
    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request, array $headers = []): ResponseInterface;
}
