<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Client\Activity\ActivityExecutionDescription;
use Temporal\Client\Activity\ActivityHandle;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\ActivityClient\CancelInput;
use Temporal\Interceptor\ActivityClient\DescribeInput;
use Temporal\Interceptor\ActivityClient\GetResultInput;
use Temporal\Interceptor\ActivityClient\StartInput;
use Temporal\Interceptor\ActivityClient\TerminateInput;
use Temporal\Interceptor\Trait\ActivityClientCallsInterceptorTrait;
use Temporal\Internal\Interceptor\Interceptor;

/**
 * @see ActivityClientCallsInterceptorTrait
 */
interface ActivityClientCallsInterceptor extends Interceptor
{
    /**
     * @param callable(StartInput): ActivityHandle $next
     */
    public function start(StartInput $input, callable $next): ActivityHandle;

    /**
     * @param callable(GetResultInput): ?ValuesInterface $next
     */
    public function getResult(GetResultInput $input, callable $next): ?ValuesInterface;

    /**
     * @param callable(DescribeInput): ActivityExecutionDescription $next
     */
    public function describe(DescribeInput $input, callable $next): ActivityExecutionDescription;

    /**
     * @param callable(CancelInput): void $next
     */
    public function cancel(CancelInput $input, callable $next): void;

    /**
     * @param callable(TerminateInput): void $next
     */
    public function terminate(TerminateInput $input, callable $next): void;
}
