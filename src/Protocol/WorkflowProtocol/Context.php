<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\WorkflowProtocol;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;

class Context
{
    /**
     * @var string|int|null
     */
    public $runId;

    /**
     * @var \DateTimeImmutable
     */
    public \DateTimeImmutable $now;

    /**
     * @var array|Deferred[]
     */
    public array $promises = [];

    /**
     * @param \DateTimeZone $zone
     * @throws \Exception
     */
    public function __construct(\DateTimeZone $zone)
    {
        $this->now = new \DateTimeImmutable('now', $zone);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function promiseForRequest(RequestInterface $request): PromiseInterface
    {
        return ($this->promises[$request->getId()] = new Deferred())->promise();
    }
}
