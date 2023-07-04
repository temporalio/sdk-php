<?php

declare(strict_types=1);

namespace Temporal\Tests\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Tests\DTO\{A, B};

#[ActivityInterface(prefix: "DynamicObjectReturnActivity.")]
class DynamicObjectReturnActivity
{
    #[ActivityMethod('doSomething')]
    public function doSomething(string $type): object
    {
        if ($type === 'a') {
            $class = new A();
            $class->a = 'testA';
        } else {
            $class = new B();
            $class->b = 'testB';
        }

        return $class;
    }
}
