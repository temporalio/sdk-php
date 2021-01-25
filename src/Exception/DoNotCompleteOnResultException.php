<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception;

class DoNotCompleteOnResultException extends TemporalException implements NonThrowableExceptionInterface
{
    /**
     * @var string
     */
    protected const DEFAULT_ERROR_MESSAGE = 'doNotCompleteOnReturn';

    /**
     * @return static
     */
    public static function create(): self
    {
        return new static(static::DEFAULT_ERROR_MESSAGE);
    }
}
