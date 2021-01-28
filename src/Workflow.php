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
use Temporal\Internal\Transport\CompletableResultInterface;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Internal\Workflow\ChildWorkflowProxy;
use Temporal\Internal\Workflow\ContinueAsNewProxy;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowInfo;

/**
 * @method static array getArguments()
 * @method static WorkflowInfo getInfo()
 * @method static mixed getLastCompletionResult($type = null)
 * @method static string getRunId()
 *
 * @method static CarbonTimeZone getTimeZone()
 * @method static \DateTimeInterface now()
 * @method static bool isReplaying()
 *
 * @method static CancellationScopeInterface async(callable $handler)
 * @method static CancellationScopeInterface asyncDetached($handler)
 *
 * @method static CompletableResultInterface await(...$condition)
 * @method static CompletableResultInterface awaitWithTimeout($interval, ...$condition)
 *
 * @method static CompletableResultInterface sideEffect(callable $cb)
 * @method static CompletableResultInterface timer(string|int|float|\DateInterval $interval)
 * @method static CompletableResultInterface getVersion(string $changeID, int $minSupported, int $maxSupported)
 *
 * @method static WorkflowContextInterface registerQuery(string $queryType, callable $handler)
 * @method static WorkflowContextInterface registerSignal(string $signalType, callable $handler)
 *
 * @method static CompletableResultInterface continueAsNew(string $name, array $args = [], ContinueAsNewOptions $options = null)
 * @method static ContinueAsNewProxy|object newContinueAsNewStub(string $class, ContinueAsNewOptions $options = null)
 *
 * @method static CompletableResultInterface executeActivity(string $name, array $args = [], ActivityOptions $options = null, \ReflectionType $returnType = null)
 * @method static ActivityProxy|object newActivityStub(string $class, ActivityOptions $options = null)
 * @method static ActivityStubInterface newUntypedActivityStub(ActivityOptions $options = null)
 *
 * @method static CompletableResultInterface executeChildWorkflow(string $name, array $args = [], ChildWorkflowOptions $options = null, \ReflectionType $returnType = null)
 * @method static ChildWorkflowProxy|object newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null)
 * @method static ChildWorkflowStubInterface newUntypedChildWorkflowStub(string $name, ChildWorkflowOptions $options = null)
 */
final class Workflow extends Facade
{
}
