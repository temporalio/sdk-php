<?php

declare(strict_types=1);

namespace Temporal\Testing\Transcript;

use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Testing\Transcript\TranscriptWriter;

final class TranscriptPlugin extends AbstractPlugin
{
    public const NAME = 'temporal-php.transcript';

    public function __construct(
        private readonly TranscriptWriter $transcript,
    ) {
        parent::__construct(self::NAME);
    }

    public function configureWorker(WorkerPluginContext $context, callable $next): void
    {
        $context->addInterceptor(new TranscriptActivityInterceptor($this->transcript));
        $context->addInterceptor(new TranscriptWorkflowInterceptor($this->transcript));
        $next($context);
    }
}
