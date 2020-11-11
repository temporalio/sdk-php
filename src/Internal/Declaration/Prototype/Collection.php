<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Prototype;

/**
 * @template-covariant T of Prototype
 * @template-implements \IteratorAggregate<string, T>
 */
final class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @var string
     */
    private const ERROR_ALREADY_EXISTS = 'Prototype with same name "%s" already has been registered';

    /**
     * @var array<string, T>
     */
    protected array $prototypes = [];

    /**
     * @param T $prototype
     * @param bool $overwrite
     */
    public function add(Prototype $prototype, bool $overwrite = false): void
    {
        $name = $prototype->getName();

        if ($overwrite === false && isset($this->prototypes[$name])) {
            throw new \OutOfBoundsException(\sprintf(self::ERROR_ALREADY_EXISTS, $name));
        }

        $this->prototypes[$name] = $prototype;
    }

    /**
     * @param string $name
     * @return T|null
     */
    public function find(string $name): ?Prototype
    {
        return $this->prototypes[$name] ?? null;
    }

    /**
     * @return \Traversable<string, T>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->prototypes);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->prototypes);
    }
}
