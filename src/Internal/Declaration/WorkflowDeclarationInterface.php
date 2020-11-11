<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

/**
 * @template-implements HandledDeclarationInterface<WorkflowInterface, WorkflowMethod>
 */
interface WorkflowDeclarationInterface extends DeclarationInterface
{
    /**
     * @return WorkflowInterface
     */
    public function getMetadata(): WorkflowInterface;

    /**
     * @return WorkflowMethod
     */
    public function getMethod(): WorkflowMethod;

    /**
     * @psalm-return iterable<QueryMethod, \ReflectionFunctionAbstract>
     *
     * @return \ReflectionFunctionAbstract[]
     */
    public function getQueryHandlers(): iterable;

    /**
     * @psalm-return iterable<SignalMethod, \ReflectionFunctionAbstract>
     *
     * @return \ReflectionFunctionAbstract[]
     */
    public function getSignalHandlers(): iterable;
}
