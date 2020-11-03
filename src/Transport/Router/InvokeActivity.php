<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Activity;
use Temporal\Client\Activity\ActivityContext;
use Temporal\Client\Activity\ActivityDeclarationInterface;
use Temporal\Client\Activity\ActivityInfo;
use Temporal\Client\Worker\Declaration\CollectionInterface;

final class InvokeActivity extends Route
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
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $info = ($context = new ActivityContext($payload))->getInfo();

        $handler = $this->findDeclarationOrFail($info)
            ->getHandler()
        ;

        try {
            Activity::setCurrentContext($context);
            $resolver->resolve(
                $handler(...$context->getArguments())
            );
        } finally {
            Activity::setCurrentContext(null);
        }
    }

    /**
     * @param ActivityInfo $info
     * @return ActivityDeclarationInterface
     */
    private function findDeclarationOrFail(ActivityInfo $info): ActivityDeclarationInterface
    {
        /** @var ActivityDeclarationInterface $activity */
        $activity = $this->activities->find($info->type->name);

        if ($activity === null) {
            $error = \sprintf('Activity with the specified name %s was not registered', $info->type->name);
            throw new \LogicException($error);
        }

        return $activity;
    }
}
