<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Transport\FutureInterface;
use Temporal\Client\Workflow\ActivityProxy;
use Temporal\Client\Workflow\CancellationScope;
use Temporal\Client\Workflow\WorkflowContextInterface;
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
 * @method static ActivityProxy|object newActivityStub(string $class, array|ActivityOptions $options = null)
 * @method static CancellationScope|object newCancellationScope(callable $handler)
 * @method static FutureInterface sideEffect(callable $cb)
 * @method static FutureInterface complete(mixed $result = null)
 * @method static FutureInterface executeActivity(string $class, array $args, array|ActivityOptions $options = null)
 * @method static FutureInterface timer(string|int|float|\DateInterval $interval)
 * @method static FutureInterface getVersion(string $changeID, int $minSupported, int $maxSupported)
 *
 * @method static WorkflowContextInterface registerQuery(string $queryType, callable $handler)
 * @method static WorkflowContextInterface registerSignal(string $signalType, callable $handler)
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
     * @var WorkflowContextInterface|null
     */
    private static ?WorkflowContextInterface $ctx = null;

    /**
     * @param WorkflowContextInterface|null $ctx
     */
    public static function setCurrentContext(?WorkflowContextInterface $ctx): void
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
        $context = self::getCurrentContext();

        return $context->$name(...$arguments);
    }

    /**
     * @return WorkflowContextInterface
     */
    private static function getCurrentContext(): WorkflowContextInterface
    {
        if (self::$ctx === null) {
            throw new \RuntimeException(self::ERROR_NO_WORKFLOW_CONTEXT);
        }

        return self::$ctx;
    }
}
