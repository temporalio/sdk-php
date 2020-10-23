<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Declaration\Repository;

use Temporal\Client\Activity\ActivityDeclaration;
use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Worker\Declaration\Collection;
use Temporal\Client\Worker\Declaration\CollectionInterface;
use Temporal\Client\Meta\ReaderInterface;

/**
 * @mixin ActivityRepositoryInterface
 */
trait ActivityRepositoryTrait
{
    /**
     * @psalm-var CollectionInterface<ActivityDeclarationInterface>
     *
     * @var CollectionInterface|ActivityDeclarationInterface[]
     */
    private CollectionInterface $activities;

    /**
     * {@inheritDoc}
     * @return ActivityRepositoryInterface|$this
     */
    public function registerActivity(object $activity, bool $overwrite = false): self
    {
        if ($activity instanceof ActivityDeclarationInterface) {
            return $this->registerActivityDeclaration($activity, $overwrite);
        }

        $activities = ActivityDeclaration::fromObject($activity, $this->getReader());

        foreach ($activities as $declaration) {
            $this->registerActivityDeclaration($declaration, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     * @return ActivityRepositoryInterface|$this
     */
    public function registerActivityDeclaration(ActivityDeclarationInterface $activity, bool $overwrite = false): self
    {
        $this->activities->add($activity, $overwrite);

        return $this;
    }

    /**
     * @return ReaderInterface
     */
    abstract protected function getReader(): ReaderInterface;

    /**
     * {@inheritDoc}
     */
    public function findActivity(string $name): ?ActivityDeclarationInterface
    {
        return $this->activities->find($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): iterable
    {
        return $this->activities;
    }

    /**
     * @return void
     */
    protected function bootActivityRepositoryTrait(): void
    {
        $this->activities = new Collection();
    }
}
