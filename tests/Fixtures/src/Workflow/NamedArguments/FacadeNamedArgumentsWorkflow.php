<?php

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class FacadeNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ): \Generator|array {

        Workflow::executeChildWorkflow(
            SimpleNamedArgumentsWorkflow::class,
            [
                'input' => $string,
                'optionalBool' => $bool,
                'optionalNullableString' => $nullableString,
            ],
            Workflow\ChildWorkflowOptions::new(),
        );
        return [
            'int' => $int,
            'string' => $string,
            'bool' => $bool,
            'nullableString' => $nullableString,
            'array' => $array,
        ];
    }
}