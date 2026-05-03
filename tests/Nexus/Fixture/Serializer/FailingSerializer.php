<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Serializer;

use Temporal\Nexus\Serializer\Internal\Content;
use Temporal\Nexus\Serializer\Internal\SerializerInterface;

/** Serializer that always throws on `serialize()`. */
final class FailingSerializer implements SerializerInterface
{
    public function serialize(mixed $value): Content
    {
        throw new \RuntimeException('cannot serialize');
    }

    public function deserialize(Content $content, string $type): mixed
    {
        return $content->data;
    }
}
