<?php

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ExecuteChildNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ): \Generator|array {
        $oneParamRes = yield Workflow::newChildWorkflowStub(SimpleNamedArgumentsWorkflow::class)->handler(
            int: $int,
        );

        $paramsInDifferentOrderRes = yield Workflow::newChildWorkflowStub(SimpleNamedArgumentsWorkflow::class)->handler(
            string: $string,
            int: $int,
            bool: $bool,
            nullableString: $nullableString,
            array: $array,
        );

        $missingParamsRes = yield Workflow::newChildWorkflowStub(SimpleNamedArgumentsWorkflow::class)->handler(
            int: $int,
            nullableString: $nullableString,
        );

        $missingParamAndDifferentOrderRes = yield Workflow::newChildWorkflowStub(SimpleNamedArgumentsWorkflow::class)->handler(
            nullableString: $nullableString,
            int: $int,
        );

        return [
            'oneParamRes' => $oneParamRes,
            'paramsInDifferentOrderRes' => $paramsInDifferentOrderRes,
            'missingParamsRes' => $missingParamsRes,
            'missingParamAndDifferentOrderRes' => $missingParamAndDifferentOrderRes,
        ];
    }
}