<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Plugin;

use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Tests\Acceptance\App\Interceptor\TranscriptActivityInterceptor;
use Temporal\Tests\Acceptance\App\Interceptor\TranscriptWorkflowInterceptor;

final class TranscriptPlugin extends AbstractPlugin
{
    public const NAME = 'temporal-php.transcript';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function configureWorker(WorkerPluginContext $context, callable $next): void
    {
        $context->addInterceptor(new TranscriptActivityInterceptor());
        $context->addInterceptor(new TranscriptWorkflowInterceptor());
        $next($context);
    }
}
