<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use JetBrains\PhpStorm\Pure;

abstract class Options
{
    /**
     * @var Diff
     */
    protected Diff $diff;

    /**
     * Options constructor.
     */
    public function __construct()
    {
        $this->diff = new Diff($this);
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        $properties = \get_object_vars($this);
        unset($properties['diff']);

        $properties['#defaults'] = $this->diff->getDefaultProperties();
        $properties['#changed'] = $this->diff->getChangedProperties($this);

        return $properties;
    }

    /**
     * @return static
     */
    #[Pure]
    public static function new(): static
    {
        return new static();
    }
}
