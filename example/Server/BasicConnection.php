<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Server;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Temporal\Client\Worker\Uuid4;

final class BasicConnection extends Connection
{
    /**
     * @param LoopInterface $loop
     * @param ConnectionInterface $connection
     * @param LoggerInterface $logger
     * @throws \JsonException
     */
    public function __construct(LoopInterface $loop, ConnectionInterface $connection, LoggerInterface $logger)
    {
        parent::__construct($loop, $connection, $logger);

        $this->process(function () {
            return $this->start();
        });
    }

    /**
     * @throws \JsonException
     */
    private function start(): \Generator
    {
        // Fetch info from client
        $result = yield $this->request('GetWorkerInfo');

        // Execute workflows
        foreach ($result['workflows'] as ['name' => $name]) {
            $info = yield $this->request('StartWorkflow', [
                'name'      => $name,
                'wid'       => 'WorkerId<' . Uuid4::create() . '>',
                'rid'       => 'WorkflowRunId<' . Uuid4::create() . '>',
                'taskQueue' => 'WorkerTaskQueue<' . Uuid4::create() . '>',
                'payload'   => [1, 2, 3],
            ]);
        }
    }

    protected function onCommand(string $name)
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
