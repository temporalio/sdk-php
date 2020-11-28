<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Client;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * @psalm-import-type ReloadGroupFlags from ReloadGroup
 */
interface ClientInterface
{
    /**
     * @param ReloadGroup $group
     * @return iterable
     */
    #[ExpectedValues(flagsFromClass: ReloadGroup::class)]
    public function reload(int $group = ReloadGroup::GROUP_ALL): iterable;

    /**
     * @param string $taskToken
     * @param mixed $result
     * @return mixed
     */
    public function completeActivity(string $taskToken, $result = null);

    /**
     * @param string $name
     * @param array $arguments
     * @param array|WorkflowOptions|null $options
     * @return array
     */
    public function executeWorkflow(string $name, array $arguments = [], $options = null): array;
}
