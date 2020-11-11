<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Declaration;

/**
 * @template T of PrototypeInterface
 */
interface CollectionInterface extends \IteratorAggregate, \Countable
{
    /**
     * @psalm-param T $declaration
     *
     * @param DeclarationInterface $declaration
     * @param bool $overwrite
     * @return void
     */
    public function add(DeclarationInterface $declaration, bool $overwrite = false): void;

    /**
     * @psalm-param T $declaration
     *
     * @param DeclarationInterface $declaration
     * @return bool
     */
    public function has(DeclarationInterface $declaration): bool;

    /**
     * @psalm-return T|null
     *
     * @param string $name
     * @return DeclarationInterface|null
     */
    public function find(string $name): ?DeclarationInterface;
}
