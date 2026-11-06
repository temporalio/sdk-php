<?php

declare(strict_types=1);

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
    ): array {
        $oneParamRes = Workflow::executeChildWorkflow(
            'SimpleNamedArgumentsWorkflow',
            [
                'int' => $int,
            ],
        );

        $paramsInDifferentOrderRes = Workflow::executeChildWorkflow(
            'SimpleNamedArgumentsWorkflow',
            [
                'string' => $string,
                'int' => $int,
                'bool' => $bool,
                'nullableString' => $nullableString,
                'array' => $array,
            ],
        );

        $missingParamsRes = Workflow::executeChildWorkflow(
            'SimpleNamedArgumentsWorkflow',
            [
                'int' => $int,
                'nullableString' => $nullableString,
            ],
        );

        $missingParamAndDifferentOrderRes = Workflow::executeChildWorkflow(
            'SimpleNamedArgumentsWorkflow',
            [
                'nullableString' => $nullableString,
                'int' => $int,
            ],
        );

        return [
            'oneParamRes' => $oneParamRes,
            'paramsInDifferentOrderRes' => $paramsInDifferentOrderRes,
            'missingParamsRes' => $missingParamsRes,
            'missingParamAndDifferentOrderRes' => $missingParamAndDifferentOrderRes,
        ];
    }
}
