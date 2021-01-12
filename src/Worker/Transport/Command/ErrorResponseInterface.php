<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

interface ErrorResponseInterface extends ResponseInterface
{
    /**
     * @return \Throwable
     */
    public function getFailure(): \Throwable;
}
