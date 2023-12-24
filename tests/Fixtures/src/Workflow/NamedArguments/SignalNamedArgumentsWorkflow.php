<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class SignalNamedArgumentsWorkflow
{
    private int $int = 0;
    private string $string;
    private bool $bool;
    private ?string $nullableString = null;
    private array $array = [];

    #[WorkflowMethod]
    public function handler(): \Generator|array
    {
        yield Workflow::await(fn() => $this->int !== 0);

        return [
            'int' => $this->int,
            'string' => $this->string,
            'bool' => $this->bool,
            'nullableString' => $this->nullableString,
            'array' => $this->array,
        ];
    }

    #[Workflow\SignalMethod]
    public function setValues(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ): void {
        $this->int = $int;
        $this->string = $string;
        $this->bool = $bool;
        $this->nullableString = $nullableString;
        $this->array = $array;
    }
}
