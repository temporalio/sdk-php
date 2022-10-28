<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;

class SignalWorkflowWithInheritanceImpl implements SignalledWorkflowWithInheritance
{
    private array $values = [];

    public function addValue(string $value)
    {
        $this->values[] = $value;
    }

    public function run(int $count)
    {
        yield Workflow::await(fn() => \count($this->values) === $count);

        return $this->values;
    }
}
