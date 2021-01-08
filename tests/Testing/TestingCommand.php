<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Testing;

use PHPUnit\Framework\Assert;
use Temporal\Worker\Transport\Command\CommandInterface;

/**
 * @template-covariant T of CommandInterface
 */
abstract class TestingCommand implements CommandInterface
{
    /**
     * @var T
     */
    protected CommandInterface $command;

    /**
     * @param T $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
    }

    /**
     * @return T
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    /**
     * {@inheritDoc}
     */
    public function getID(): int
    {
        return $this->command->getID();
    }

    /**
     * @param int $expected
     * @param string $message
     * @return $this
     */
    public function assertId(int $expected, string $message = ''): self
    {
        Assert::assertSame($expected, $this->getID(), $message);

        return $this;
    }
}
