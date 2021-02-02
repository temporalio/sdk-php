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
    /**
     * @var string
     */
    private string $data;

    /**
     * @param string $data
     */
    public function __construct(string $data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return strlen($this->data);
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }
}
