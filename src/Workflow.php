<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Future\FutureInterface;
use Temporal\Client\Workflow\ActivityProxy;
use Temporal\Client\Workflow\WorkflowEnvironmentInterface;
use Temporal\Client\Workflow\WorkflowInfo;

/**
 * @method static string getProcessId()
 * @method static array getArguments()
 * @method static WorkflowInfo getInfo()
 *
 * @method static \DateTimeInterface now()
 * @method static int[] getSendRequestIdentifiers()
 * @method static bool isReplaying()
 *
 * @method static ActivityProxy newActivityStub(string $class)
 * @method static PromiseInterface sideEffect(callable $cb)
 * @method static PromiseInterface complete(mixed $result = null)
 * @method static FutureInterface executeActivity(string $class, array $args, array|ActivityOptions $options = null)
 * @method static FutureInterface timer(string|int|float|\DateInterval $interval)
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
     * @var WorkflowEnvironmentInterface|null
     */
    private static ?WorkflowEnvironmentInterface $ctx = null;

    /**
     * @param WorkflowEnvironmentInterface|null $ctx
     */
    public static function setCurrentEnvironment(?WorkflowEnvironmentInterface $ctx): void
    {
        self::$ctx = $ctx;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::getCurrentEnvironment();

        return $context->$name(...$arguments);
    }

    /**
     * @return WorkflowEnvironmentInterface
     */
    private static function getCurrentEnvironment(): WorkflowEnvironmentInterface
    {
        if (self::$ctx === null) {
            throw new \RuntimeException(self::ERROR_NO_WORKFLOW_CONTEXT);
        }

        return self::$ctx;
    }
}
