<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Client;

use JetBrains\PhpStorm\ArrayShape;
use Spiral\Attributes\AttributeReader;
use Temporal\Client\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Client\Internal\Marshaller\Marshaller;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Worker\Transport\ConnectionInterface;

class ClientConnection implements ClientInterface
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        $this->marshaller = new Marshaller(
            new AttributeMapperFactory(
                new AttributeReader()
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function reload(int $group = ReloadGroup::GROUP_ALL): iterable
    {
        $result = [];

        if (($group & ReloadGroup::GROUP_ACTIVITIES) === ReloadGroup::GROUP_ACTIVITIES) {
            $result[ReloadGroup::GROUP_ACTIVITIES] =
                $this->connection->call('resetter.Reset', 'activities')
            ;
        }

        if (($group & ReloadGroup::GROUP_WORKFLOWS) === ReloadGroup::GROUP_WORKFLOWS) {
            $result[ReloadGroup::GROUP_WORKFLOWS] =
                $this->connection->call('resetter.Reset', 'workflows')
            ;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function completeActivity(string $taskToken, $result = null)
    {
        return $this->connection->call('temporal.CompleteActivity', [
            'taskToken' => $taskToken,
            'result'    => $result,
        ]);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @param array|WorkflowOptions|null $options
     * @return array
     * @throws \ReflectionException
     */
    #[ArrayShape(['taskQueue' => 'string', 'executionTimeout' => 'int', 'runTimeout' => 'int', 'taskTimeout' => 'int', 'namespace' => 'string', 'attempt' => 'int'])]
    public function executeWorkflow(string $name, array $arguments = [], $options = null): array
    {
        return $this->connection->call('temporal.ExecuteWorkflow', [
            'name'    => $name,
            'input'   => $arguments,
            'options' => $this->marshaller->marshal(
                $this->options($options, WorkflowOptions::class)
            ),
        ]);
    }

    /**
     * @psalm-template T of object
     *
     * @param T|array|null $options
     * @param class-string<T> $class
     * @return T
     * @throws \ReflectionException
     */
    private function options($options, string $class): object
    {
        switch (true) {
            case $options === null:
                return new $class();

            case \is_array($options):
                return $this->marshaller->unmarshal($options, new $class());

            case $options instanceof $class:
                return $options;

            default:
                throw new \InvalidArgumentException('Invalid options argument');
        }
    }
}
