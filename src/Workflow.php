<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Carbon\CarbonTimeZone;
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Transport\FutureInterface;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Internal\Workflow\ChildWorkflowProxy;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowInfo;

/**
 * @method static array<Payload> getArguments()
 * @method static WorkflowInfo getInfo()
 *
 * @method static string getRunId()
 *
 * @method static CarbonTimeZone getTimeZone()
 * @method static \DateTimeInterface now()
 * @method static bool isReplaying()
 *
 * @method static CancellationScopeInterface newCancellationScope(callable $handler)
 *
 * @method static FutureInterface executeActivity(string $class, array $args = [], array|ActivityOptions $options = null)
 * @method static ActivityProxy|object newActivityStub(string $class, array|ActivityOptions $options = null)
 *
 * @method static FutureInterface executeChildWorkflow(string $type, array $args = [], ChildWorkflowOptions $options = null)
 * @method static ChildWorkflowProxy|object newChildWorkflowStub(string $class, array|ChildWorkflowOptions $options = null)
 *
 * @method static FutureInterface sideEffect(callable $cb)
 * @method static FutureInterface complete(mixed $result = null)
 * @method static FutureInterface timer(string|int|float|\DateInterval $interval)
 * @method static FutureInterface getVersion(string $changeID, int $minSupported, int $maxSupported)
 *
 * @method static WorkflowContextInterface registerQuery(string $queryType, callable $handler)
 * @method static WorkflowContextInterface registerSignal(string $signalType, callable $handler)
 *
 * @method static FutureInterface continueAsNew(string $name, ...$input)
 */
final class Workflow extends Facade
{
}
