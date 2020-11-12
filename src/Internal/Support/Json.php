<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Support;

final class Json
{
    /**
     * @var int
     */
    private const JSON_MAX_DEPTH = 512;

    /**
     * @psalm-pure
     * @throws \JsonException
     */
    private static function handleJsonErrors(): void
    {
        /** @psalm-suppress ImpureFunctionCall */
        [$code, $message] = [\json_last_error(), \json_last_error_msg()];

        if ($code !== \JSON_ERROR_NONE) {
            throw new \JsonException($message, $code);
        }
    }

    /**
     * @psalm-pure
     *
     * @param mixed $value
     * @param int $options
     * @return string
     * @throws \JsonException
     */
    public static function encode($value, int $options = 0): string
    {
        if (\defined('\\JSON_THROW_ON_ERROR')) {
            $options |= \JSON_THROW_ON_ERROR;
        }

        $result = \json_encode($value, $options, self::JSON_MAX_DEPTH);

        self::handleJsonErrors();

        return $result;
    }

    /**
     * @psalm-pure
     *
     * @param string $json
     * @param int $options
     * @return mixed
     * @throws \JsonException
     */
    public static function decode(string $json, int $options = 0)
    {
        if (\defined('\\JSON_THROW_ON_ERROR')) {
            $options |= \JSON_THROW_ON_ERROR;
        }

        $assoc = $options & \JSON_OBJECT_AS_ARRAY;

        $result = \json_decode($json, (bool)$assoc, self::JSON_MAX_DEPTH, $options);

        self::handleJsonErrors();

        return $result;
    }
}
