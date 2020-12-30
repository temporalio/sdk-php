<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Mapper;

/**
 * @psalm-type Getter = \Closure(): mixed
 * @psalm-type Setter = \Closure(mixed): void
 */
interface MapperInterface
{
    /**
     * @return bool
     */
    public function isCopyOnWrite(): bool;

    /**
     * @return iterable<string, Getter>
     */
    public function getGetters(): iterable;

    /**
     * @return iterable<string, Setter>
     */
    public function getSetters(): iterable;
}
