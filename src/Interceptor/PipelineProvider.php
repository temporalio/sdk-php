<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Interceptor\Pipeline;

/**
 * Provide {@see Pipeline} of specific type of {@see Interceptor}.
 */
interface PipelineProvider
{
    /**
     * @template T of Interceptor
     *
     * @param class-string<T> $interceptorClass Only interceptors of this type will be returned in pipeline.
     *
     * @return Pipeline<T, mixed>
     */
    public function getPipeline(string $interceptorClass): Pipeline;
}
