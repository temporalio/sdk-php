<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Command\RequestInterface;

class Context
{
    /**
     * @readonly
     * @var string|int|null
     */
    public $runId;

    /**
     * @readonly
     * @var \DateTimeInterface
     */
    public \DateTimeInterface $now;

    /**
     * @readonly
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
     * @param int $id
     * @return Deferred
     */
    public function fetch(int $id): Deferred
    {
        $deferred = $this->promises[$id] ?? null;

        if ($deferred === null) {
            throw new \OutOfBoundsException('The received response does not match any existing request');
        }

        unset($this->promises[$id]);

        return $deferred;
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function promiseForRequest(RequestInterface $request): PromiseInterface
    {
        $this->promises[$request->getId()] = $deferred = new Deferred();

        return $deferred->promise();
    }
}
