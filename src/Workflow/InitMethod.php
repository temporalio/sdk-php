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

/**
 * Marks a Workflow constructor to be executed with the {@see WorkflowMethod} arguments.
 * The Init Method is executed before the Workflow Method, Signal, and Update handlers.
 *
 * @Annotation
 * @Target({ "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class InitMethod {}
