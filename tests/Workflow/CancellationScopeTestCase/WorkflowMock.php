<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Workflow\CancellationScopeTestCase;

use Temporal\Client\Promise;
use Temporal\Client\Workflow;

class WorkflowMock
{
    private $data;

    public function first()
    {
        $scope = Workflow::newCancellationScope(function () {
            yield Workflow::executeActivity('activity');

            return 42;
        });

        $scope->cancel();

        return 0xDEAD_BEEF;
    }

    public function second()
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

    public function simpleNested()
    {
        return yield Workflow::newCancellationScope(function() {
            return yield Workflow::newCancellationScope(function () {
                return 42;
            });
        });
    }

    public function race()
    {
        $promise = Workflow::executeActivity('first');

        yield Workflow::executeActivity('second');

        return yield $promise;
    }

    public function simpleNestingScopeCancelled()
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

    public function memoizedPromise()
    {
        $scope = Workflow::newCancellationScope(function() {
            $this->data = Workflow::executeActivity('example');

            return 42;
        });

        $scope->cancel();

        return yield $this->data;
    }
}
