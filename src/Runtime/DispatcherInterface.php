<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use React\Promise\Deferred;
use Temporal\Client\Protocol\Message\RequestInterface;

interface DispatcherInterface
{
    /**
     * @param RequestInterface $request
     * @param Deferred $resolver
     */
    public function emit(RequestInterface $request, Deferred $resolver): void;
}
