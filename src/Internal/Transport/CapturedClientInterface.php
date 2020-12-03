<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport;

use Temporal\Client\Worker\Command\RequestInterface;

interface CapturedClientInterface extends ClientInterface, \Countable, \IteratorAggregate
{
    /**
     * @return array<RequestInterface>
     */
    public function getUnresolvedRequests(): array;

    /**
     * @return array<RequestInterface>
     */
    public function fetchUnresolvedRequests(): array;
}
