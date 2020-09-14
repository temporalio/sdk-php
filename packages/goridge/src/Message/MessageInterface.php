<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge\Message;

/**
 * @property-read string $body
 * @property-read int $size
 */
interface MessageInterface extends \Stringable
{
}
