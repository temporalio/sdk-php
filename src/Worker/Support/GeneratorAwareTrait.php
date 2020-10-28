<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Support;

trait GeneratorAwareTrait
{
    /**
     * @param iterable $generators
     * @return \Generator
     */
    public function cooperative(iterable $generators): \Generator
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
