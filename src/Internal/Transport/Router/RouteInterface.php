<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

interface RouteInterface
{
    /**
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * @throws \Throwable
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void;
}
