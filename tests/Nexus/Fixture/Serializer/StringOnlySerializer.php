<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\Serializer;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Serializer\Internal\Content;
use Temporal\Nexus\Serializer\Internal\SerializerInterface;

final class StringOnlySerializer implements SerializerInterface
{
    public function serialize(mixed $value): Content
    {
        if ($value === null) {
            return new Content('');
        }
        if (!\is_string($value)) {
            throw new InvalidArgumentException('Only string types accepted');
        }
        return new Content($value);
    }

    public function deserialize(Content $content, string $type): mixed
    {
        if ($type !== 'string') {
            throw new InvalidArgumentException('Only string types accepted');
        }
        return $content->data;
    }
}
