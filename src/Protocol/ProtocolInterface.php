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

interface ProtocolInterface extends ClientInterface
{
    /**
     * @param string $request
     * @param array $headers
     * @return string
     */
    public function next(string $request, array $headers): string;
}
