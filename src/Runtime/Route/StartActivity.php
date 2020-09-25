<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Route;

use React\Promise\Deferred;
use Temporal\Client\Declaration\ActivityInterface;
use Temporal\Client\Declaration\CollectionInterface;
use Temporal\Client\Protocol\ClientInterface;

class StartActivity extends Route
{
    /**
     * @psalm-var CollectionInterface<ActivityInterface>
     *
     * @var CollectionInterface
     */
    private CollectionInterface $activities;

    /**
     * @psalm-param CollectionInterface<ActivityInterface> $activities
     *
     * @param CollectionInterface $activities
     * @param ClientInterface $client
     */
    public function __construct(CollectionInterface $activities, ClientInterface $client)
    {
        $this->activities = $activities;
    }

    /**
     * @param array $params
     * @return string|null
     */
    private function getErrorMessage(array $params): ?string
    {
        if (! isset($params['name']) || ! \is_string($params['name'])) {
            return 'Required field "name" is missing or contains an invalid type';
        }

        if (! isset($params['wid']) || ! \is_string($params['wid'])) {
            return 'Required field "wid" is missing or contains an invalid type';
        }

        if (! isset($params['rid']) || ! \is_string($params['rid'])) {
            return 'Required field "wid" is missing or contains an invalid type';
        }

        if (! isset($params['arguments']) || ! \is_array($params['arguments'])) {
            return 'Required field "arguments" contains an invalid type';
        }

        return null;
    }

    /**
     * @param array $params
     * @param Deferred $resolver
     */
    public function handle(array $params, Deferred $resolver): void
    {
        if ($error = $this->getErrorMessage($params)) {
            throw new \InvalidArgumentException($error);
        }

        $activity = $this->activities->find($params['name']);

        if ($activity === null) {
            $error = \sprintf('Activity "%s" has not been registered', $params['name']);
            throw new \InvalidArgumentException($error);
        }

        throw new \LogicException('Activity execution not implemented yet');
    }
}
