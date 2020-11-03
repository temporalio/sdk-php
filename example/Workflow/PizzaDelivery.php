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
use Temporal\Client\FutureInterface;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

class PizzaDelivery
{
    private $value = 0;

    /** @QueryMethod() */
    public function get()
    {
        return $this->value;
    }

    /** @SignalMethod() */
    public function add(int $value): void
    {
        $this->value += $value;
    }

    /** @WorkflowMethod(name="PizzaDelivery") */
    public function handler(Workflow\WorkflowContextInterface $ctx, $input)
    {
        /** @var \Temporal\Client\Future\Future $act */
        $act = Workflow::activity(ExampleActivity::class)->a('test');

        dump('act done');
        yield Workflow::timer(2);

        dump([
            $act->isComplete(),
            $act->getValue()
        ]);

        // 1. resolve
        // 2. invoke callbacks ======> [DONE] <=======
        // 3. loop

        /**
         * @var FutureInterface $a
         */

//        $a = $ctx->executeActivity('App\\Activity\\ExampleActivity::a', ['A'])  // оплата
//                 ->then(function () use ($ctx) {
//                      return $ctx->executeActivity('App\\Activity\\ExampleActivity::b', ['B']); // на счет
//                 });
//
//        $ctx->registerSignalHandler('cancelA', function () use ($a) {  // отменить оплату
//            if (!$a->isComplete()) {
//                $a->cancel();
//            } else {
//                // TODO: REFUND
//            }
//        });

        // BATCH: [A, S]

        //     yield $a;


        //yield $ctx->executeActivity('App\\Activity\\ExampleActivity::b', ['B']);
        //dump($a);
//
//        if (Promise::isResolved($a)) {
//            dump('HELLO');
//        } else {
//            dump('BAD');
//        }

        //        yield Promise::all([
        //            Workflow::activity(ExampleActivity::class)->a(1),
        //            Workflow::activity(ExampleActivity::class)->a(2)
        //        ]);

        //        while (true) {
        //            yield Workflow::timer(30);
        //
        //            if ($this->value > 0) {
        //                $this->value--;
        //                if (!Workflow::isReplaying()) {
        //                    dump('deducted! ' . $this->value);
        //                }
        //
        //                yield Workflow::activity(ExampleActivity::class)->a(1);
        //            }
        //        }

        //        yield $ctx->activity(ExampleActivity::class)->a('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');
        //        $this->int += 200;
        //        yield $ctx->activity(ExampleActivity::class)->a('BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB');
        //        $this->int += 300;
        //        yield $ctx->activity(ExampleActivity::class)->a('CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC');
        //        $this->int += 400;
        //        yield $ctx->activity(ExampleActivity::class)->a('DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD');
        //        $this->int += 500;
        //        return 'OOOOOOOOOOOOOOOKKKKK';
        return 'ok';
    }

//    private function test($index, $value)
//    {
//        // Cyril from a
//        // Antony from a
//        $a = yield Workflow::activity(ExampleActivity::class)
//                           ->a($value);
//
//        // cyril from b
//        // antony from b
//        $b = yield Workflow::activity(ExampleActivity::class)
//                           ->b($value);
//
//        // FIRST: cyril from a from b
//        // SECOND: antony from a from b
//        return $index . ': ' . $a . $b;
//    }
//
//    private function test2($index, $value)
//    {
//        return yield Workflow::activity(ExampleActivity::class)
//                             ->a($value);
//    }

}
