<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Temporal\Api\Common\V1\Payloads;
use Temporal\DataConverter\PayloadComparator;

class InvocationMatched
{
    /** @var list<array{args: string, result: InvocationResult|InvocationFailure}> */
    private array $cases = [];

    public function addCase(Payloads $args, InvocationResult|InvocationFailure $result): void
    {
        $this->cases[] = ['args' => $args->serializeToJsonString(), 'result' => $result];
    }

    public function match(Payloads $input): InvocationResult|InvocationFailure|null
    {
        foreach ($this->cases as $case) {
            $expected = new Payloads();
            $expected->mergeFromJsonString($case['args']);

            if (PayloadComparator::equals($input, $expected)) {
                return $case['result'];
            }
        }

        return null;
    }
}
