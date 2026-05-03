<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceDefinition;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

#[Service]
interface ValidServiceWithOperations extends ValidSuperServiceWithOperations
{
    #[Operation]
    public function superMethod(): void;

    #[Operation]
    public function noParamNoReturn(): void;

    #[Operation]
    public function noParamSingleReturn(): string;

    #[Operation]
    public function singleParamNoReturn(string $param): void;

    #[Operation]
    public function singleParamSingleReturn(string $param): string;

    #[Operation(name: 'custom-name')]
    public function customName(): void;
}
