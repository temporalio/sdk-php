<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\Service;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\WorkflowHandle;

#[Service]
interface CancelSignaturesServiceInterface
{
    #[AsyncOperation(output: 'string')]
    public function legacy(string $name): WorkflowHandle;

    #[AsyncOperation(output: 'string')]
    public function contextAndDetails(string $name): WorkflowHandle;

    #[AsyncOperation(output: 'string')]
    public function reversed(string $name): WorkflowHandle;

    #[AsyncOperation(output: 'string')]
    public function noArgs(string $name): WorkflowHandle;
}
