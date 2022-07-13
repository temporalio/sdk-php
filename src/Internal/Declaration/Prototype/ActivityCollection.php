<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Closure;
use Temporal\Internal\Repository\ArrayRepository;

/**
 * @template-extends ArrayRepository<ActivityPrototype>
 */
final class ActivityCollection extends ArrayRepository
{
    private ?Closure $finalizer = null;

    public function addFinalizer(Closure $finalizer): void
    {
        $this->finalizer = $finalizer;
    }

    public function getFinalizer(): ?Closure
    {
        return $this->finalizer;
    }
}
