<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

final class Bytes implements \Stringable
{
    private string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function getSize(): int
    {
        return \strlen($this->data);
    }

    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->data;
    }
}
