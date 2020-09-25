<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

interface MessageInterface
{
    /**
     * @return string|int
     */
    public function getId();

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @param int $options
     * @return string
     * @throws \JsonException
     */
    public function toJson(int $options = 0): string;
}
