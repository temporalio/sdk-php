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

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateWorkflow(string $name): void
    {
        self::validateCommonPrefix($name, 'A Workflow type');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateQueryMethod(string $name): void
    {
        $name === '__stack_trace' || $name === '__enhanced_stack_trace' and throw new \InvalidArgumentException(
            "The Query method name `$name` is reserved for built-in functionality and cannot be used.",
        );

        self::validateCommonPrefix($name, 'A Query method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateSignalMethod(string $name): void
    {
        self::validateCommonPrefix($name, 'A Signal method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateUpdateMethod(string $name): void
    {
        self::validateCommonPrefix($name, 'An Update method');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateActivity(string $name): void
    {
        self::validateCommonPrefix($name, 'An Activity type');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function validateTaskQueue(string $name): void
    {
        self::validateCommonPrefix($name, 'A Task Queue name');
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
                "%s cannot start with the internal prefix `%s`.",
                $target,
                self::COMMON_BUILTIN_PREFIX,
            ),
        );
    }
}
