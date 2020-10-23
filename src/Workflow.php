<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Workflow\Runtime\Process;
use Temporal\Client\Workflow\Runtime\WorkflowContextInterface;

/**
 * @method static string getName()
 */
final class Workflow
{
    /**
     * @var string
     */
    private const ERROR_NO_WORKFLOW_CONTEXT =
        'Calling workflow methods can only be made from ' .
        'the currently running workflow process';

    /**
     * @var Process|null
     */
    private static ?Process $process = null;

    /**
     * @param Process $process
     */
    public static function setCurrentProcess(Process $process): void
    {
        self::$process = $process;
    }

    /**
     * @return Process
     */
    private static function getCurrentProcess(): Process
    {
        if (self::$process === null) {
            throw new \RuntimeException(self::ERROR_NO_WORKFLOW_CONTEXT);
        }

        return self::$process;
    }

    /**
     * @return WorkflowContextInterface
     */
    private static function getContext(): WorkflowContextInterface
    {
        $process = self::getCurrentProcess();

        return $process->getContext();
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::getContext();

        return $context->$name(...$arguments);
    }
}
