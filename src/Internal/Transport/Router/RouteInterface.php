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
     * @return string
     */
    public function getName(): string;

    /**
     * @param ServerRequestInterface $request
     * @param array $headers
     * @param Deferred $resolver
     * @return void
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void;
}
