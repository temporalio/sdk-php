<?php

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class ChildSignalNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ): \Generator|array {
        // one param
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = $childStub->handler();

        $childStub->setValues(
            int: $int,
        );

        $oneParamRes = yield $run;

        // params in different order
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = $childStub->handler();

        $childStub->setValues(
            string: $string,
            int: $int,
            bool: $bool,
            nullableString: $nullableString,
            array: $array,
        );

        $paramsInDifferentOrderRes = yield $run;

        // missing params
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = $childStub->handler();

        $childStub->setValues(
            int: $int,
            nullableString: $nullableString,
        );

        $missingParamsRes = yield $run;

        // missing param and different order
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = $childStub->handler();

        $childStub->setValues(
            nullableString: $nullableString,
            int: $int,
        );

        $missingParamAndDifferentOrderRes = yield $run;

        return [
            'oneParamRes' => $oneParamRes,
            'paramsInDifferentOrderRes' => $paramsInDifferentOrderRes,
            'missingParamsRes' => $missingParamsRes,
            'missingParamAndDifferentOrderRes' => $missingParamAndDifferentOrderRes,
        ];
    }
}