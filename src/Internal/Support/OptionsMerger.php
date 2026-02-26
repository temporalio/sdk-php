<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

/**
 * Provides merge logic for Options subclasses and attribute-based options building.
 *
 * @internal
 */
final class OptionsMerger
{
    /**
     * Merge changed properties from $source into a clone of $target.
     *
     * - Properties that hold an {@see Options} instance are deep-merged via {@see Options::applyChangedFrom()}.
     * - Null values from $source are skipped (they mean "not set").
     * - Properties that don't exist in $target are silently skipped (safe for cross-type merges).
     *
     * @template T of Options
     * @param T $target
     * @return T
     */
    public static function merge(Options $target, ?Options $source): Options
    {
        $self = clone $target;

        if ($source === null || !$source->hasChanges()) {
            return $self;
        }

        foreach ($source->getChangedPropertyNames() as $name) {
            if (!\property_exists($self, $name)) {
                continue;
            }

            $value = $source->$name;

            // Deep-merge nested Options (e.g. RetryOptions)
            if ($value instanceof Options) {
                $current = $self->$name ?? $value::new();
                $self->$name = clone $current;
                $self->$name->applyChangedFrom($value);
                continue;
            }

            if ($value !== null) {
                $self->$name = $value;
            }
        }

        return $self;
    }

    /**
     * Merge granular attribute-based options from a class/method hierarchy level.
     *
     * Priority (lowest → highest): $previous → class attributes → method attributes.
     *
     * @template T of Options
     * @param T $classOptions  Options from class-level attributes
     * @param T $methodOptions Options from method-level attributes
     * @param T|null $previous Options from a previous hierarchy level
     * @return T
     */
    public static function mergeHierarchy(Options $classOptions, Options $methodOptions, ?Options $previous): Options
    {
        // Class attributes as base, method attributes override
        $options = self::merge($classOptions, $methodOptions);

        // Previous hierarchy level as base, current level overrides
        if ($previous !== null) {
            $options = self::merge($previous, $options);
        }

        return $options;
    }

    /**
     * Read PHP attributes from a reflection target and apply them to an Options instance
     * using a map of attribute class → builder callback.
     *
     * Each builder receives the current options and the attribute instance,
     * and must return a new Options instance.
     *
     * @template T of Options
     * @param T $options
     * @param array<class-string, \Closure> $builders Map of attribute class → fn(T, attribute): T
     * @return T
     */
    public static function applyAttributes(
        Options $options,
        \ReflectionMethod|\ReflectionClass $reflection,
        array $builders,
    ): Options {
        foreach ($builders as $attributeClass => $builder) {
            $attrs = $reflection->getAttributes($attributeClass);
            if (isset($attrs[0])) {
                $options = $builder($options, $attrs[0]->newInstance());
            }
        }

        return $options;
    }
}
