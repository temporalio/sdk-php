<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Internal\Repository\ArrayRepository;

/**
 * @template-extends ArrayRepository<WorkflowPrototype>
 */
final class WorkflowCollection extends ArrayRepository
{
}
