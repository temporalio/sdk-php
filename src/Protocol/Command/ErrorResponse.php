<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Command;

class ErrorResponse extends Response implements ErrorResponseInterface
{
    /**
     * @var string
     */
    protected string $message;

    /**
     * @var int
     */
    protected int $code;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @param string $message
     * @param int $code
     * @param mixed $data
     * @param int|null $id
     */
    public function __construct(string $message, int $code, $data, int $id = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;

        parent::__construct($id);
    }

    /**
     * @psalm-param class-string<\Throwable>
     * @param string $class
     * @return \Throwable
     */
    public function toException(string $class): \Throwable
    {
        return new $class($this->message, $this->code);
    }

    /**
     * @param \Throwable $e
     * @param int|null $id
     * @return static
     */
    public static function fromException(\Throwable $e, int $id = null): self
    {
        $data = [];

        foreach ($e->getTrace() as $item) {
            $data[] = $item['file'] . ':' . $item['line'];
        }

        return new static($e->getMessage(), $e->getCode(), $data, $id);
    }

    /**
     * {@inheritDoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        return $this->data;
    }
}
