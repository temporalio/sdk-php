<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Protocol;

interface TypeInterface
{
    /**
     * @var int
     */
    public const TYPE_MESSAGE = 0x00;

    /**
     * @var int
     */
    public const TYPE_ERROR = 0x01;

    /**
     * @var int
     */
    public const TYPE_CONTROL = 0x02;
}
