<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

class Diff
{
    /**
     * @var int
     */
    private const PROPERTY_FLAGS = \ReflectionProperty::IS_PUBLIC;

    /**
     * @var string
     */
    private const ERROR_INVALID_CONTEXT = 'Passed context object must be an instance of "%s" class, but "%s" given';

    /**
     * @var string
     */
    private const ERROR_INVALID_PROPERTY = 'The context object "%s" does not contain the property named "%s"';

    /**
     * @var string
     */
    private string $class;

    /**
     * @var array<string, mixed>
     */
    private array $properties = [];

    /**
     * @param object $context
     */
    public function __construct(object $context)
    {
        $reflection = new \ReflectionObject($context);

        $this->class = $reflection->getName();

        foreach ($reflection->getProperties(self::PROPERTY_FLAGS) as $property) {
            $this->properties[$property->getName()] = $property->getValue($context);
        }
    }

    /**
     * @param object $context
     * @param string|null $property
     * @return bool
     */
    public function isPresent(object $context, string $property = null): bool
    {
        return !$this->isChanged($context, $property);
    }

    /**
     * @param object $context
     * @param string|null $property
     * @return bool
     */
    public function isChanged(object $context, string $property = null): bool
    {
        $this->matchContext($context);

        if ($property === null) {
            return $this->isChangedAnyProperty($context);
        }

        if (!\array_key_exists($property, $this->properties)) {
            $message = \sprintf(self::ERROR_INVALID_PROPERTY, $this->class, $property);
            throw new \InvalidArgumentException($message);
        }

        return $this->properties[$property] !== $context->$property;
    }

    /**
     * @param object $context
     * @return array<string>
     */
    public function getPresentPropertyNames(object $context): array
    {
        return \array_keys($this->getPresentProperties($context));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param object $context
     * @return array<string, mixed>
     */
    public function getPresentProperties(object $context): array
    {
        $changed = $this->getChangedPropertyNames($context);
        $filter = static fn ($_, string $name): bool => !\in_array($name, $changed, true);

        return \array_filter($this->properties, $filter, \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param object $context
     * @return array<string>
     */
    public function getChangedPropertyNames(object $context): array
    {
        return \array_keys($this->getChangedProperties($context));
    }

    /**
     * @param object $context
     * @return array<string, mixed>
     */
    public function getChangedProperties(object $context): array
    {
        $this->matchContext($context);

        $result = [];

        foreach ($this->properties as $name => $value) {
            if ($context->$name !== $value) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * @param object $context
     */
    private function matchContext(object $context): void
    {
        $actual = \get_class($context);

        if ($this->class !== $actual) {
            $message = \sprintf(self::ERROR_INVALID_CONTEXT, $this->class, $actual);
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @param object $context
     * @return bool
     */
    private function isChangedAnyProperty(object $context): bool
    {
        foreach ($this->properties as $name => $value) {
            if ($context->$name !== $value) {
                return true;
            }
        }

        return false;
    }
}
