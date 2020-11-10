<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

abstract class Declaration implements DeclarationInterface
{
    /**
     * @var object
     */
    protected object $meta;

    /**
     * @param object $meta
     */
    public function __construct(object $meta)
    {
        $this->meta = $meta;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): object
    {
        return $this->meta;
    }
}
