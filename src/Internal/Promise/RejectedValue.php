<?php

declare(strict_types=1);

namespace Temporal\Internal\Promise;

/**
 * An exception that wraps a rejected value.
 *
 * @see \Temporal\Promise::reject()
 * @internal
 */
final class RejectedValue extends \Exception
{
    public function __construct(
        public mixed $value,
    ) {
        parent::__construct();
    }
}
