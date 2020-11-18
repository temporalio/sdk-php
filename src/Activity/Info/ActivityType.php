<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity\Info;

final class ActivityType
{
    /**
     * @readonly
     * @var string
     */
    public string $name;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * TODO throw exception in case of incorrect data, not really since it driven by the server
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        return new self($data['Name']);
    }
}
