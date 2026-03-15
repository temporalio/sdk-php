<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Doctrine\Common\Annotations\Annotation\Target;
use Spiral\Attributes\NamedArgumentConstructor;
use Internal\Destroy\Destroyable;

/**
 * Marks a class or interface as a Workflow.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "CLASS" })
 *
 * To control memory usage of workflow instances you can implement {@see Destroyable} interface in the workflow class.
 * The {@see Destroyable::destroy} method will be called when workflow instance is not needed anymore
 * (for example, when workflow execution is completed, failed or need to be evicted from memory).
 * You can use this method to help with memory management by breaking circular references.
 * Note: the Workflow logger may not emit logs from within the destroy method.
 */
#[\Attribute(\Attribute::TARGET_CLASS), NamedArgumentConstructor]
class WorkflowInterface {}
