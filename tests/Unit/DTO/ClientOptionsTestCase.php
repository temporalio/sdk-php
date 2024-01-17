<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\Client\ClientOptions;
use Temporal\Common\Uuid;

class ClientOptionsTestCase extends AbstractDTOMarshalling
{
    public function testNamespaceChangesNotMutateState(): void
    {
        $dto = new ClientOptions();

        $this->assertNotSame($dto, $dto->withNamespace(
            Uuid::v4()
        ));
    }

    public function testIdentityChangesNotMutateState(): void
    {
        $dto = new ClientOptions();

        $this->assertNotSame($dto, $dto->withIdentity(
            Uuid::v4()
        ));
    }
    public function testQueryRejectionConditionChangesNotMutateState(): void
    {
        $dto = new ClientOptions();

        $this->assertNotSame($dto, $dto->withQueryRejectionCondition(
            QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY
        ));
    }
}
