<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

/**
 * @psalm-immutable
 */
final class Panic extends Request
{
    public const NAME = 'Panic';

    /**
     * @param \Throwable|null $failure
     */
    public function __construct(\Throwable $failure = null)
    {
        parent::__construct(self::NAME, [], null);
        $this->setFailure($failure);
    }
}
