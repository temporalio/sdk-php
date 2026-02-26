<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class ContinueAsNewNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ) {
        if ($int > 5) {
            // complete
            return [
                'int' => $int,
                'string' => $string,
                'bool' => $bool,
                'nullableString' => $nullableString,
                'array' => $array,
            ];
        }

        if ($int !== 1) {
            assert(!empty(Workflow::getInfo()->continuedExecutionRunId));
        }

        ++$int;

        $args = $this->shuffleArgs([
            'int' => $int,
            'string' => $string,
            'bool' => $bool,
            'nullableString' => $nullableString,
            'array' => $array,
        ]);

        return Workflow::newContinueAsNewStub(self::class)->handler(...$args);
    }

    private function shuffleArgs(array $args): array
    {
        $keys = array_keys($args);

        shuffle($keys);

        $shuffled = [];

        foreach ($keys as $key) {
            $shuffled[$key] = $args[$key];
        }

        return $shuffled;
    }
}
