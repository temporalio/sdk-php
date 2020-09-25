<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Message;

interface ErrorResponseInterface extends ResponseInterface
{
    /**
     * @var int
     */
    public const CODE_PARSE_ERROR = -32700;

    /**
     * @var int
     */
    public const CODE_INVALID_REQUEST = -32600;

    /**
     * @var int
     */
    public const CODE_METHOD_NOT_FOUND = -32601;

    /**
     * @var int
     */
    public const CODE_INVALID_PARAMETERS = -32602;

    /**
     * @var int
     */
    public const CODE_INTERNAL_ERROR = -32603;

    /**
     * @return int
     */
    public function getCode(): int;

    /**
     * @return string
     */
    public function getMessage(): string;

    /**
     * @return mixed
     */
    public function getData();

    /**
     * @param \Throwable|null $parent
     * @return \Throwable
     */
    public function toException(\Throwable $parent = null): \Throwable;
}
