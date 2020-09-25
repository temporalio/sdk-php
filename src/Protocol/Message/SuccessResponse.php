<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

final class SuccessResponse extends Message implements SuccessResponseInterface
{
    /**
     * @var mixed
     */
    private $payload;

    /**
     * @param mixed $payload
     * @param string|int|null $id
     */
    public function __construct($payload, $id = null)
    {
        $this->payload = $payload;

        parent::__construct($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getResult()
    {
        return $this->payload;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'result' => $this->getResult(),
        ]);
    }
}
