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

/**
 * @property-read RequestInterface $request
 * @property-read Deferred $resolver
 * @property-read PromiseInterface $promise
 */
interface EntryInterface
{
}
