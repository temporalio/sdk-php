<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Exception\Activity;

use Temporal\Client\Exception\TemporalException;
use Throwable;

class DoNotCompleteOnResultException extends TemporalException
{
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct("doNotCompleteOnReturn", $code, $previous);
    }
}
