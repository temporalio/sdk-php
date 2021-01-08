<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Testing;

use Temporal\Worker\Transport\Command\ErrorResponseInterface;

/**
 * @template-extends TestingCommand<ErrorResponseInterface>
 */
class TestingErrorResponse extends TestingCommand implements ErrorResponseInterface
{
    /**
     * @param ErrorResponseInterface $response
     */
    public function __construct(ErrorResponseInterface $response)
    {
        parent::__construct($response);
    }

    /**
     * {@inheritDoc}
     */
    public function getCode(): int
    {
        return $this->command->getCode();
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return $this->command->getMessage();
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        return $this->command->getData();
    }

    /**
     * {@inheritDoc}
     */
    public function toException(string $class = \LogicException::class): \Throwable
    {
        return $this->command->toException($class);
    }
}
