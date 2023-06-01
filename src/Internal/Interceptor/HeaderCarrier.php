<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Interceptor;

use Temporal\Interceptor\HeaderInterface;

/**
 * @internal
 */
interface HeaderCarrier
{
    /**
     * Get configured Header set.
     *
     * @return HeaderInterface
     *
     * @psalm-mutation-free
     */
    public function getHeader(): HeaderInterface;
}
