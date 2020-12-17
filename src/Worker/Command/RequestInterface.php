<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Command;

interface RequestInterface extends CommandInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return array
     */
    public function getParams(): array;
}
