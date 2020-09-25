<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Queue;

use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Message\RequestInterface;

/**
 * @template-implements \IteratorAggregate<EntryInterface>
 */
interface RequestQueueInterface extends \IteratorAggregate, \Countable
{
    /**
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function add(RequestInterface $request): PromiseInterface;

    /**
     * @param PromiseInterface $promise
     * @return EntryInterface|null
     */
    public function pull(PromiseInterface $promise): ?EntryInterface;
}
