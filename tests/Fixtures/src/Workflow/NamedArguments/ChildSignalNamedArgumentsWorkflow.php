<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class ChildSignalNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ): array {
        // one param
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = Workflow::async(static fn() => $childStub->handler());

        $childStub->setValues(
            int: $int,
        );

        $oneParamRes = $run->await();

        // params in different order
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = Workflow::async(static fn() => $childStub->handler());

        $childStub->setValues(
            string: $string,
            int: $int,
            bool: $bool,
            nullableString: $nullableString,
            array: $array,
        );

        $paramsInDifferentOrderRes = $run->await();

        // missing params
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = Workflow::async(static fn() => $childStub->handler());

        $childStub->setValues(
            int: $int,
            nullableString: $nullableString,
        );

        $missingParamsRes = $run->await();

        // missing param and different order
        $childStub = Workflow::newChildWorkflowStub(SignalNamedArgumentsWorkflow::class);

        $run = Workflow::async(static fn() => $childStub->handler());

        $childStub->setValues(
            nullableString: $nullableString,
            int: $int,
        );

        $missingParamAndDifferentOrderRes = $run->await();

        return [
            'oneParamRes' => $oneParamRes,
            'paramsInDifferentOrderRes' => $paramsInDifferentOrderRes,
            'missingParamsRes' => $missingParamsRes,
            'missingParamAndDifferentOrderRes' => $missingParamAndDifferentOrderRes,
        ];
    }
}
