<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceImplInstance;

use Temporal\Tests\Nexus\Fixture\Service\GenericServiceInterface;

/**
 * Parent declares the operation method; subclass inherits without redeclaring it.
 * Exercises the inheritance traversal in {@see \Temporal\Nexus\Handler\Internal\ServiceImplFactory}.
 */
class ParentWithHandler implements GenericServiceInterface
{
    public function operation(string $name): string
    {
        return 'parent';
    }
}
