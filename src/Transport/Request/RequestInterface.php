<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

use Temporal\Client\Transport\MessageInterface;

interface RequestInterface extends MessageInterface
{
    /**
     * @return string
     */
    public function getName(): string;
}
