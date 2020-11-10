<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Meta;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({ "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WorkflowInterface
{
}
