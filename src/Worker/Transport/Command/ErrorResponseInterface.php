<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

interface ErrorResponseInterface extends ResponseInterface
{
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
     * @param string $class
     * @return \Throwable
     */
    public function toException(string $class = \LogicException::class): \Throwable;
}
