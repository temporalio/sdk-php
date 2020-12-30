<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Workflow;

final class WorkflowMock
{
    private $data;

    public function workflow1()
    {
        $scope = Workflow::newCancellationScope(function () {
            yield Workflow::executeActivity('activity');

            return 42;
        });

        $scope->cancel();

        return 0xDEAD_BEEF;
    }

    public function workflow2()
    {
        $cs1 = Workflow::newCancellationScope(function() {
            return yield Workflow::executeActivity('a');
        });

        $cs2 = Workflow::newCancellationScope(function() {
            return yield Workflow::executeActivity('b');
        });

        $result = yield Promise::any([$cs1, $cs2]);

        $cs1->cancel();
        $cs2->cancel();

        return $result;
    }

    public function workflow3()
    {
        return yield Workflow::newCancellationScope(function() {
            return yield Workflow::newCancellationScope(function () {
                return 42;
            });
        });
    }

    public function workflow4()
    {
        $scope = Workflow::newCancellationScope(function() {
            $this->data = Workflow::executeActivity('example');

            return 42;
        });

        $scope->cancel();

        return yield $this->data;
    }

    public function workflow5()
    {
        $promise = Workflow::executeActivity('first');

        yield Workflow::executeActivity('second');

        return yield $promise;
    }

    public function workflow6()
    {
        return yield Workflow::newCancellationScope(function() {
            $result = Workflow::newCancellationScope(function () {
                yield Workflow::executeActivity('example');

                return 42;
            });

            $result->cancel();

            return yield $result;
        });
    }

    public function workflow7()
    {
        $result = false;

        $scope = Workflow::newCancellationScope(function () {
            yield Workflow::executeActivity('example');
        })
            ->onCancel(function () use (&$result) {
                $result = true;
            });

        $scope->cancel();

        return $result;
    }

    public function workflow8()
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(5)
        ;

        return yield Workflow::executeActivity('first', [], $options)
            ->then(function ($result) use ($options) {
                return Workflow::executeActivity(
                    'second',
                    ['Result:' . $result],
                    $options
                );
            });
    }
}
