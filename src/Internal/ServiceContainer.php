<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal;

use JetBrains\PhpStorm\Immutable;
use Spiral\Attributes\ReaderInterface;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Client\Internal\Marshaller\Marshaller;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Worker\Environment\Environment;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Worker\LoopInterface;

#[Immutable]
final class ServiceContainer
{
    /**
     * @var LoopInterface
     */
    #[Immutable]
    public LoopInterface $loop;

    /**
     * @var ClientInterface
     */
    #[Immutable]
    public ClientInterface $client;

    /**
     * @var ReaderInterface
     */
    #[Immutable]
    public ReaderInterface $reader;

    /**
     * @var EnvironmentInterface
     */
    #[Immutable]
    public EnvironmentInterface $env;

    /**
     * @var MarshallerInterface
     */
    #[Immutable]
    public MarshallerInterface $marshaller;

    /**
     * @var RepositoryInterface<WorkflowPrototype>
     */
    #[Immutable]
    public RepositoryInterface $workflows;

    /**
     * @var RepositoryInterface<ActivityPrototype>
     */
    #[Immutable]
    public RepositoryInterface $activities;

    /**
     * @param LoopInterface $loop
     * @param ClientInterface $client
     * @param ReaderInterface $reader
     */
    public function __construct(LoopInterface $loop, ClientInterface $client, ReaderInterface $reader)
    {
        $this->loop = $loop;
        $this->client = $client;
        $this->reader = $reader;

        $this->workflows = new Collection();
        $this->activities = new Collection();

        $this->env = new Environment();

        $this->marshaller = new Marshaller(
            new AttributeMapperFactory($this->reader)
        );
    }
}
