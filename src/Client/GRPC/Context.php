<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Client\GRPC;


class Context implements ContextInterface
{
    /**
     * @return Context
     */
    public static function default()
    {
        return new self();
    }
}
