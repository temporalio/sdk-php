<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow\NamedArguments;

use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SimpleNamedArgumentsWorkflow
{
    #[WorkflowMethod]
    public function handler(
        int $int,
        string $string = '',
        bool $bool = false,
        ?string $nullableString = null,
        array $array = [],
    ) {
        return [
            'int' => $int,
            'string' => $string,
            'bool' => $bool,
            'nullableString' => $nullableString,
            'array' => $array,
        ];
    }
}
