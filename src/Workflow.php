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
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Support\Facade;
use Temporal\Internal\Transport\FutureInterface;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Internal\Workflow\ChildWorkflowProxy;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowInfo;

/**
 * @method static array getArguments()
 * @method static WorkflowInfo getInfo()
 *
 * @method static string getRunId()
 *
 * @method static CarbonTimeZone getTimeZone()
 * @method static \DateTimeInterface now()
 * @method static bool isReplaying()
 *
 * @method static CancellationScopeInterface newCancellationScope(callable $handler)
 * @method static CancellationScopeInterface newDetachedCancellationScope(callable $handler)
 *
 * @method static PromiseInterface sideEffect(callable $cb)
 * @method static PromiseInterface complete(mixed $result = null)
 * @method static PromiseInterface timer(string|int|float|\DateInterval $interval)
 * @method static PromiseInterface getVersion(string $changeID, int $minSupported, int $maxSupported)
 *
 * @method static WorkflowContextInterface registerQuery(string $queryType, callable $handler)
 * @method static WorkflowContextInterface registerSignal(string $signalType, callable $handler)
 *
 * @method static FutureInterface continueAsNew(string $name, ...$input)
 *
 * @method static PromiseInterface executeActivity(string $name, array $args = [], ActivityOptions $options = null, \ReflectionType $returnType = null)
 * @method static ActivityProxy|object newActivityStub(string $class, ActivityOptions $options = null)
 * @method static ActivityStubInterface newUntypedActivityStub(ActivityOptions $options = null)
 *
 * @method static PromiseInterface executeChildWorkflow(string $name, array $args = [], ChildWorkflowOptions $options = null, \ReflectionType $returnType = null)
 * @method static ChildWorkflowProxy|object newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null)
 * @method static ChildWorkflowStubInterface newUntypedChildWorkflowStub(string $name, ChildWorkflowOptions $options = null)
 */
final class Workflow extends Facade
{
}
