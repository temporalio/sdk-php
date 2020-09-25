<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Meta;

use Temporal\Client\Meta\Doctrine\DoctrineResolver;
use Temporal\Client\Meta\Native\NativeResolver;

class Factory implements FactoryInterface
{
    /**
     * @var array|ResolverInterface[]
     */
    private array $resolvers;

    /**
     * @param array|ResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = \array_merge($this->getDefaultResolvers(), $resolvers);
    }

    /**
     * @return array|ResolverInterface[]
     */
    private function getDefaultResolvers(): array
    {
        return [
            static::PREFER_NATIVE   => new NativeResolver(),
            static::PREFER_DOCTRINE => new DoctrineResolver(),
        ];
    }

    /**
     * @psalm-param FactoryInterface::PREFER_* $type
     *
     * @param int $type
     * @return ReaderInterface
     */
    public function create(int $type = self::PREFER_NATIVE): ReaderInterface
    {
        $resolver = $this->resolvers[$type] ?? null;

        if ($resolver === null) {
            throw new \LogicException('No registered driver resolver for type #' . $type);
        }

        if ($resolver->isSupported()) {
            return $resolver->create();
        }

        return $this->createExcept($type);
    }

    /**
     * @param int $type
     * @return ReaderInterface
     */
    private function createExcept(int $type): ReaderInterface
    {
        foreach ($this->resolvers as $id => $resolver) {
            if ($id === $type) {
                continue;
            }

            if ($resolver->isSupported()) {
                return $resolver->create();
            }
        }

        throw new \LogicException('There are no metadata readers available');
    }
}
