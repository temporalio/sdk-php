<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use Temporal\Client\Internal\Support\Uuid4;
use Temporal\Client\Workflow;

class SimpleWorkflow
{
    #[Workflow\Meta\WorkflowMethod(name: )]
    public function handler(): iterable
    {
        $activities = Workflow::newActivityStub(EchoActivity::class);

        $actual = Workflow::sideEffect(fn() => Uuid4::create());

        dump('Actual UUID: ' . $actual);

        $result = yield $activities->echo($actual);

        dump('Returned UUID: ' . $result);
    }
}
