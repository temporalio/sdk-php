<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

final class ReplayFailedException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $nonDeterministic,
    ) {
        parent::__construct($message);
    }
}
