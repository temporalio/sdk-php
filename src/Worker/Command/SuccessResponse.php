<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Command;

use Temporal\Client\DataConverter\Payload;

class SuccessResponse extends Response implements SuccessResponseInterface
{
    /**
     * @var array<Payload>
     */
    protected array $result;

    /**
     * @param mixed $result
     * @param int|null $id
     */
    public function __construct($result, int $id = null)
    {
        $this->result = $this->isArray($result) ? \array_values($result) : [$result];

        parent::__construct($id);
    }

    /**
     * @param mixed $result
     * @return bool
     */
    private function isArray($result): bool
    {
        if (! \is_array($result)) {
            return false;
        }

        foreach (\array_keys($result) as $key) {
            if (! \is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getResult(): array
    {
        return $this->result;
    }
}
