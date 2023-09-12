<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

abstract class Route implements RouteInterface
{
    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->getShortClassName();
    }

    /**
     * @return non-empty-string
     */
    private function getShortClassName(): string
    {
        /** @var non-empty-list<non-empty-string> $chunks */
        $chunks = \explode('\\', static::class);

        return \array_pop($chunks);
    }
}
