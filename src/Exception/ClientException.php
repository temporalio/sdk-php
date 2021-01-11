<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception;


use Throwable;

class ClientException extends \RuntimeException
{
    /**
     * @var \stdClass
     */
    private \stdClass $status;

    /**
     * ClientException constructor.
     * @param \stdClass $status
     * @param Throwable|null $previous
     */
    public function __construct(\stdClass $status, Throwable $previous = null)
    {
        parent::__construct($status->details, $status->code, $previous);
    }

    /**
     * @return \stdClass
     */
    public function getStatus(): \stdClass
    {
        return $this->status;
    }
}
