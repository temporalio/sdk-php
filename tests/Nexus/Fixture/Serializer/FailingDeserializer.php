<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Serializer;

use Temporal\Nexus\Serializer\Content;
use Temporal\Nexus\Serializer\SerializerInterface;

/** Deserializer that always throws {@see \JsonException}. */
final class FailingDeserializer implements SerializerInterface
{
    public function serialize(mixed $value): Content
    {
        return new Content((string) $value);
    }

    public function deserialize(Content $content, string $type): mixed
    {
        throw new \JsonException('Bad JSON');
    }
}
