<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Internal\Marshaller\Meta\Marshal;

final class NexusOperationContext
{
    public function __construct(
        #[Marshal(name: 'namespace')]
        public string $namespace = '',
        #[Marshal(name: 'taskQueue')]
        public string $taskQueue = '',
    ) {}
}
