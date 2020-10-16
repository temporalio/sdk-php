<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Protocol;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\ProtocolInterface;

interface WorkflowProtocolInterface extends ProtocolInterface
{
    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface;

    /**
     * @param string $request
     * @return string
     */
    public function next(string $request): string;

    /**
     * @return \DateTimeInterface
     */
    public function getCurrentTickTime(): \DateTimeInterface;
}
