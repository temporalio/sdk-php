<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Workflow;

use App\Activity\ExampleActivity;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

class PizzaDelivery
{
    private int $value = 0xDEAD_BEEF;

    /** @QueryMethod() */
    public function get(): int
    {
        return $this->value;
    }

    /** @WorkflowMethod(name="PizzaDelivery") */
    public function handler(): iterable
    {
        Workflow::registerQuery('get_dynamic', fn () => $this->value);

        yield Workflow::timer(40);

        $a = Workflow::newActivityStub(ExampleActivity::class, [
            'scheduleToCloseTimeout' => '3h 42s'
        ]);

        yield $a->a('test');
        yield $a->b('asdasd');

//        $value = yield Workflow::sideEffect(function () {
//            return mt_rand(0, 1000);
//        });
//
//        $version = yield Workflow::getVersion("test", self::DEFAULT_VERSION, 2);
//        dump($version);
//
//        yield Workflow::timer(10);
//
//        return $value;
//        $expire = Workflow::timer(60);
//
//        while (true) {
//            if ($expire->isComplete()) {
//                break;
//            }
//
//            $this->value++;
//            yield Workflow::timer(1);
//        }
        //return $this->value;
    }

    /** @SignalMethod() */
    public function add(int $value): void
    {
        $this->value += $value;
    }
}
