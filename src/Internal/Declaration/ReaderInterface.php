<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

/**
 * @template-covariant T of DeclarationInterface
 */
interface ReaderInterface
{
    /**
     * @psalm-return iterable<int, T>
     *
     * @param string $class
     * @return DeclarationInterface[]
     */
    public function fromClass(string $class): iterable;
}
