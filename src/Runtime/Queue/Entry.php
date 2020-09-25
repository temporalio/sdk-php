<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Queue;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\Message\RequestInterface;

final class Entry implements EntryInterface
{
    /**
     * @psalm-readonly
     * @var RequestInterface
     */
    public RequestInterface $request;

    /**
     * @psalm-readonly
     * @var Deferred
     */
    public Deferred $resolver;

    /**
     * @psalm-readonly
     * @var PromiseInterface
     */
    public PromiseInterface $promise;

    /**
     * @param RequestInterface $request
     * @param Deferred $resolver
     */
    public function __construct(RequestInterface $request, Deferred $resolver)
    {
        $this->request = $request;
        $this->resolver = $resolver;
        $this->promise = $resolver->promise();
    }
}
