<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Client\Activity\ActivityExecutionDescription;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\ActivityClient\CancelInput;
use Temporal\Interceptor\ActivityClient\DescribeInput;
use Temporal\Interceptor\ActivityClient\GetResultInput;
use Temporal\Interceptor\ActivityClient\StartInput;
use Temporal\Interceptor\ActivityClient\StartOutput;
use Temporal\Interceptor\ActivityClient\TerminateInput;

trait ActivityClientCallsInterceptorTrait
{
    public function start(StartInput $input, callable $next): StartOutput
    {
        return $next($input);
    }

    public function getResult(GetResultInput $input, callable $next): ?ValuesInterface
    {
        return $next($input);
    }

    public function describe(DescribeInput $input, callable $next): ActivityExecutionDescription
    {
        return $next($input);
    }

    public function cancel(CancelInput $input, callable $next): void
    {
        $next($input);
    }

    public function terminate(TerminateInput $input, callable $next): void
    {
        $next($input);
    }
}
