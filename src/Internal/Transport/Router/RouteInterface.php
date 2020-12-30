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

interface RouteInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param array $payload
     * @param array $headers
     * @param Deferred $resolver
     * @return void
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void;
}
