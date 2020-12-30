<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use JetBrains\PhpStorm\ExpectedValues;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-import-type ReloadGroupFlags from ReloadGroup
 */
interface ClientInterface
{
    /**
     * @param ReloadGroupFlags $group
     * @return iterable
     */
    #[ExpectedValues(flagsFromClass: ReloadGroup::class)]
    public function reload(int $group = ReloadGroup::GROUP_ALL): iterable;

    /**
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowOptions|null $options
     * @return object<T>|T
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): object;

    /**
     * @param string $name
     * @param WorkflowOptions|null $options
     * @return WorkflowStubInterface
     */
    public function newUntypedWorkflowStub(string $name, WorkflowOptions $options = null): WorkflowStubInterface;

    /**
     * @return ActivityCompletionClientInterface
     */
    public function newActivityClient(): ActivityCompletionClientInterface;
}
