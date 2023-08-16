<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

interface ResponseInterface extends CommandInterface
{
    /**
     * @return int<0, max>
     */
    public function getHistoryLength(): int;
}
