<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use Temporal\Client\Protocol\Message\ResponseInterface;

interface ServerInterface extends ProtocolInterface
{
    /**
     * @param ResponseInterface $response
     */
    public function reply(ResponseInterface $response): void;
}
