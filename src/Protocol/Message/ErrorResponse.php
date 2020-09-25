<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

use Spiral\Goridge\Exception\TransportException;

final class ErrorResponse extends Message implements ErrorResponseInterface
{
    /**
     * @var string
     */
    private string $message;

    /**
     * @var int
     */
    private int $code;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @param string|int|null $id
     */
    public function __construct(string $message, int $code, $data = null, $id = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;

        parent::__construct($id);
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function toException(\Throwable $parent = null): \Throwable
    {
        return new TransportException($this->getMessage(), $this->getCode(), $parent);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'error' => [
                'code'    => $this->getCode(),
                'message' => $this->getMessage(),
                // TODO "error.data" member MAY be omitted.
                'data'    => $this->getData(),
            ],
        ]);
    }
}
