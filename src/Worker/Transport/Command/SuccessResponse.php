<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

use Temporal\DataConverter\Payload;

class SuccessResponse extends Response implements SuccessResponseInterface
{
    /**
     * @var array<Payload>
     */
    protected array $result;

    /**
     * @param array<Payload> $result
     * @param int|null $id
     */
    public function __construct(array $result, int $id = null)
    {
        $this->result = $result;

        parent::__construct($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getPayloads(): array
    {
        return $this->result;
    }
}
