<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;

#[WorkflowInterface]
class SimpleUuidWorkflow
{
    #[WorkflowMethod(name: 'SimpleUuidWorkflow')]
    #[ReturnType(UuidInterface::class)]
    public function handler(UuidInterface $uuid)
    {
        // Side effect
        $seUuid = yield Workflow::sideEffect(static fn(): UuidInterface => Uuid::uuid4());
        if (!$seUuid instanceof UuidInterface) {
            throw new \RuntimeException('Invalid type');
        }
        // UUID
        $newUuid = yield Workflow::uuid();
        if (!$newUuid instanceof UuidInterface) {
            throw new \RuntimeException('Invalid UUID type');
        }
        // UUID4
        $uuid4 = yield Workflow::uuid4();
        if (!$uuid4 instanceof UuidInterface) {
            throw new \RuntimeException('Invalid UUID4 type');
        }
        // UUID7
        $uuid7 = yield Workflow::uuid7(Workflow::now());
        if (!$uuid7 instanceof UuidInterface) {
            throw new \RuntimeException('Invalid UUID7 type');
        }

        return $uuid;
    }
}
