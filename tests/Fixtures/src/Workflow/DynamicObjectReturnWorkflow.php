<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\DTO\A;
use Temporal\Tests\DTO\B;
use Temporal\DataConverter\Type;
use Temporal\Activity\ActivityOptions;

#[Workflow\WorkflowInterface]
class DynamicObjectReturnWorkflow
{
    #[WorkflowMethod]
    public function start(): iterable
    {
        $opts = ActivityOptions::new()->withStartToCloseTimeout(5);

        $cp = 0;
        $result = yield Workflow::executeActivity(
            'DynamicObjectReturnActivity.doSomething',
            ['a'],
            $opts,
            A::class
        );
        if ($result instanceof A) {
            ++$cp;
        }

        $result = yield Workflow::executeActivity(
            'DynamicObjectReturnActivity.doSomething',
            ['b'],
            $opts,
            new \ReflectionClass(B::class)
        );
        if ($result instanceof B) {
            ++$cp;
        }

        $result = yield Workflow::executeActivity('DynamicObjectReturnActivity.doSomething', ['a'], $opts);
        if ($result instanceof \stdClass) {
            ++$cp;
        }

        $result = yield Workflow::executeActivity(
            'DynamicObjectReturnActivity.doSomething',
            ['b'],
            $opts,
            Type::fromReflectionClass(new \ReflectionClass(B::class))
        );
        if ($result instanceof B) {
            ++$cp;
        }

        return $cp === 4 ? 'OK' : 'ERROR';
    }
}
