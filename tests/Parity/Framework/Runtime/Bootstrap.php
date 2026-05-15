<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Runtime;

use Temporal\Testing\DeprecationCollector;
use Temporal\Worker\FeatureFlags;

final class Bootstrap
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        \ini_set('display_errors', 'stderr');
        \error_reporting(-1);

        DeprecationCollector::register();

        FeatureFlags::$workflowDeferredHandlerStart = true;
        FeatureFlags::$cancelAbandonedChildWorkflows = false;
        FeatureFlags::$warnOnActivityMethodWithoutAttribute = true;
    }
}
