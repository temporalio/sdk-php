<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * URI + type that decodes it. Constructor rejects empty values.
 *
 * @see https://github.com/nexus-rpc/api/blob/main/SPEC.md (Nexus-Link header)
 */
final class Link implements \Stringable
{
    public function __construct(
        public readonly string $uri,
        public readonly string $type,
    ) {
        if ($uri === '') {
            throw new InvalidArgumentException('Link URI must not be empty');
        }
        if ($type === '') {
            throw new InvalidArgumentException('Link type must not be empty');
        }
    }

    /**
     * @param iterable<array-key, mixed> $links
     * @param non-empty-string $where Label used in the error message, e.g. `Foo: links`.
     */
    public static function assertAll(iterable $links, string $where): void
    {
        foreach ($links as $i => $link) {
            if (!$link instanceof self) {
                throw new InvalidArgumentException(\sprintf(
                    '%s[%s] must be a %s, got %s',
                    $where,
                    \is_int($i) ? (string) $i : \var_export($i, true),
                    self::class,
                    \get_debug_type($link),
                ));
            }
        }
    }

    public function __toString(): string
    {
        return "Link{uri='{$this->uri}', type='{$this->type}'}";
    }
}
