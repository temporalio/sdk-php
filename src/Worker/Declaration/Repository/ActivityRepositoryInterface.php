<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Declaration\Repository;

use Temporal\Client\Activity\ActivityDeclarationInterface;

interface ActivityRepositoryInterface
{
    /**
     * @param object $activity
     * @param bool $overwrite
     * @return static
     */
    public function registerActivity(object $activity, bool $overwrite = false): self;

    /**
     * @param ActivityDeclarationInterface $activity
     * @param bool $overwrite
     * @return static
     */
    public function registerActivityDeclaration(ActivityDeclarationInterface $activity, bool $overwrite = false): self;

    /**
     * @param string $name
     * @return ActivityDeclarationInterface|null
     */
    public function findActivity(string $name): ?ActivityDeclarationInterface;

    /**
     * @return iterable|ActivityDeclarationInterface[]
     */
    public function getActivities(): iterable;
}
