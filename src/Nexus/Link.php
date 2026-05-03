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
 * URI + type that decodes it. Constructor rejects empty values; use
 * {@see self::fromUri()} for an absolute-URI check.
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
     * Strict variant: requires an absolute URI with an explicit scheme.
     *
     * @throws InvalidArgumentException
     */
    public static function fromUri(string $uri, string $type): self
    {
        $parsed = \parse_url($uri);
        if ($parsed === false || ($parsed['scheme'] ?? '') === '') {
            throw new InvalidArgumentException(
                "Link URI must be absolute with a scheme (e.g. 'https://example.com/x'); got '{$uri}'",
            );
        }
        return new self($uri, $type);
    }

    /**
     * Encode as RFC 8288 `<uri>; type="..."`. `\` and `"` in `type` are escaped.
     */
    public function toHeaderValue(): string
    {
        $escapedType = \addcslashes($this->type, "\\\"");
        return "<{$this->uri}>; type=\"{$escapedType}\"";
    }

    public function __toString(): string
    {
        return "Link{uri='{$this->uri}', type='{$this->type}'}";
    }
}
