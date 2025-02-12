<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Interceptor;

use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowOutboundCallsInterceptorTrait;
use Temporal\Interceptor\Trait\WorkflowOutboundRequestInterceptorTrait;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundRequestInterceptor;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * Check that interceptor traits cover all interfaces methods.
 *
 * @group unit
 * @group interceptor
 */
class TraitsTestCase extends AbstractUnit
{
    public function testActivityInboundInterceptor(): void
    {
        new class implements ActivityInboundInterceptor {
            use ActivityInboundInterceptorTrait;
        };

        self::assertTrue(true);
    }

    public function testWorkflowClientCallsInterceptor(): void
    {
        new class implements WorkflowClientCallsInterceptor {
            use WorkflowClientCallsInterceptorTrait;
        };

        self::assertTrue(true);
    }

    public function testWorkflowInboundCallsInterceptor(): void
    {
        new class implements WorkflowInboundCallsInterceptor {
            use WorkflowInboundCallsInterceptorTrait;
        };

        self::assertTrue(true);
    }

    public function testWorkflowOutboundCallsInterceptor(): void
    {
        new class implements WorkflowOutboundCallsInterceptor {
            use WorkflowOutboundCallsInterceptorTrait;
        };

        self::assertTrue(true);
    }

    public function testWorkflowOutboundRequestInterceptor(): void
    {
        new class implements WorkflowOutboundRequestInterceptor {
            use WorkflowOutboundRequestInterceptorTrait;
        };

        self::assertTrue(true);
    }
}
