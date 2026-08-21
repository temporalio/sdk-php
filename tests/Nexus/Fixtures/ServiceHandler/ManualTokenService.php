<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceHandler;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;

#[Service(name: 'ManualTokenService')]
final class ManualTokenService
{
    public readonly ExternalJobHandler $externalJobHandler;

    public function __construct()
    {
        $this->externalJobHandler = new ExternalJobHandler();
    }

    #[AsyncOperation(output: 'string', input: 'string')]
    public function startExternal(): ExternalJobHandler
    {
        return $this->externalJobHandler;
    }

    #[AsyncOperation(output: 'string', input: 'string')]
    public function startUncancellable(): UncancellableJobHandler
    {
        return new UncancellableJobHandler();
    }

    #[AsyncOperation(output: 'string', input: 'string')]
    public function startAlreadyFinished(): FinishedJobHandler
    {
        return new FinishedJobHandler();
    }
}
