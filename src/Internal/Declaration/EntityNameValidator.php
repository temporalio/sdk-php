<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

/**
 * Temporal entity name validator.
 *
 * @internal
 */
final class EntityNameValidator
{
    public const COMMON_BUILTIN_PREFIX = '__temporal_';
    public const QUERY_TYPE_STACK_TRACE = "__stack_trace";
    public const ENHANCED_QUERY_TYPE_STACK_TRACE = "__enhanced_stack_trace";

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateWorkflow(string $name): void
    {
        self::validateCommonPrefix($name, 'Workflow name');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateQueryMethod(string $name): void
    {
        if ($name === self::QUERY_TYPE_STACK_TRACE || $name === self::ENHANCED_QUERY_TYPE_STACK_TRACE) {
            throw new \InvalidArgumentException(
                "The Query method name `$name` is reserved for built-in functionality and cannot be used.",
            );
        }

        self::validateCommonPrefix($name, 'Query method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateSignalMethod(string $name): void
    {
        self::validateCommonPrefix($name, 'Signal method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateUpdateMethod(string $name): void
    {
        self::validateCommonPrefix($name, 'Update method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateActivity(string $name): void
    {
        self::validateCommonPrefix($name, 'Activity type');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateTaskQueue(string $name): void
    {
        self::validateCommonPrefix($name, 'Task Queue name');
    }

    /**
     * Throws an exception if the name starts with the common built-in prefix.
     *
     * @throws \InvalidArgumentException
     */
    private static function validateCommonPrefix(string $name, string $target): void
    {
        \str_starts_with($name, self::COMMON_BUILTIN_PREFIX) and throw new \InvalidArgumentException(
            \sprintf(
                "%s must not start with the internal prefix `%s`.",
                $target,
                self::COMMON_BUILTIN_PREFIX,
            ),
        );
    }
}
