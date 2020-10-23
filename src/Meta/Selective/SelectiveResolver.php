<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Meta\Selective;

use Temporal\Client\Meta\Doctrine\DoctrineResolver;
use Temporal\Client\Meta\Native\NativeResolver;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Meta\ResolverInterface;

class SelectiveResolver implements ResolverInterface
{
    /**
     * @var ResolverInterface[]
     */
    private array $resolvers;

    /**
     * @param ResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    /**
     * {@inheritDoc}
     */
    public function isSupported(): bool
    {
        foreach ($this->resolvers as $resolver) {
            if (! $resolver->isSupported()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function create(): ReaderInterface
    {
        $readers = [];

        foreach ($this->resolvers as $resolver) {
            $readers[] = $resolver->create();
        }

        return new SelectiveReader($readers);
    }
}
