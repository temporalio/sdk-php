<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use Temporal\Client\Activity\ActivityContext;
use Temporal\Client\Activity\ActivityContextInterface;
use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Worker\Declaration\CollectionInterface;

final class StartActivity extends Route
{
    /**
     * @var CollectionInterface
     */
    private CollectionInterface $activities;

    /**
     * @psalm-param CollectionInterface<ActivityDeclarationInterface> $activities
     *
     * @param CollectionInterface $activities
     */
    public function __construct(CollectionInterface $activities)
    {
        $this->activities = $activities;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers)
    {
        $context = new ActivityContext($payload);

        $declaration = $this->findDeclarationOrFail($context);

        $handler = $declaration->getHandler();

        return $handler($context, ...$context->getArguments());
    }

    /**
     * @param ActivityContextInterface $context
     * @return ActivityDeclarationInterface
     */
    private function findDeclarationOrFail(ActivityContextInterface $context): ActivityDeclarationInterface
    {
        /** @var ActivityDeclarationInterface $activity */
        $activity = $this->activities->find($context->getName());

        if ($activity === null) {
            $error = \sprintf('Activity with the specified name %s was not registered', $context->getName());
            throw new \LogicException($error);
        }

        return $activity;
    }
}
