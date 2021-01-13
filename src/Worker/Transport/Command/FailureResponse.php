<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

/**
 * Carries failure response.
 */
class FailureResponse extends Response implements FailureResponseInterface
{
    protected \Throwable $failure;

    /**
     * @param \Throwable $failure
     * @param int|null $id
     */
    public function __construct(\Throwable $failure, int $id = null)
    {
        $this->failure = $failure;
        parent::__construct($id);
    }

    /**
     * @return \Throwable
     */
    public function getFailure(): \Throwable
    {
        return $this->failure;
    }
}
