<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

abstract class Options
{
    protected Diff $diff;

    /**
     * Options constructor.
     */
    public function __construct()
    {
        $this->diff = new Diff($this);
    }

    /**
     * @psalm-pure
     * @psalm-return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Create a new instance of the called class, transferring changed properties from $source.
     * Only properties that exist in both classes and differ from defaults in $source are copied.
     */
    public static function fromOptions(Options $source): static
    {
        $target = self::new();

        foreach ($source->diff->getChangedPropertyNames($source) as $name) {
            if (\property_exists($target, $name)) {
                $target->$name = $source->$name;
            }
        }

        return $target;
    }

    /**
     * Apply changed properties from $source onto this instance.
     * Only properties that exist in both objects and differ from defaults in $source are copied.
     */
    public function applyChangedFrom(Options $source): void
    {
        foreach ($source->diff->getChangedPropertyNames($source) as $name) {
            if (\property_exists($this, $name)) {
                $this->$name = $source->$name;
            }
        }
    }

    /**
     * @return array<array-key, string>
     * @internal
     */
    public function getChangedPropertyNames(): array
    {
        return $this->diff->getChangedPropertyNames($this);
    }

    /**
     * Whether any public property has been changed from its default value.
     *
     * @internal
     */
    public function hasChanges(): bool
    {
        return $this->diff->isChanged($this);
    }

    public function __debugInfo(): array
    {
        $properties = \get_object_vars($this);
        unset($properties['diff']);

        $properties['#defaults'] = $this->diff->getDefaultProperties();
        $properties['#changed'] = $this->diff->getChangedProperties($this);

        return $properties;
    }
}
