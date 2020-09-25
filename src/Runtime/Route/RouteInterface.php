<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Route;

use React\Promise\Deferred;

interface RouteInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param array $params
     * @param Deferred $resolver
     */
    public function handle(array $params, Deferred $resolver): void;
}
