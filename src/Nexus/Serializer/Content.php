<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Serializer;

use Temporal\Nexus\Internal\Headers;

/**
 * Data + headers used by serializers.
 */
final class Content
{
    /** @var array<string, string> Lowercased keys. */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers Lowercased on construction.
     */
    public function __construct(
        public readonly string $data,
        array $headers = [],
    ) {
        $this->headers = Headers::normalize($headers);
    }
}
