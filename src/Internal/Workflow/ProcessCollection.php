<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Workflow\Process\Process;

/**
 * @template-extends ArrayRepository<Process>
 */
class ProcessCollection extends ArrayRepository
{
    /**
     * @var string
     */
    private const ERROR_PROCESS_NOT_DEFINED = 'Unable to kill workflow because workflow process #%s was not found';

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    /**
     * @param string $runId
     * @return Process
     */
    public function pull(string $runId): Process
    {
        /** @var Process $process */
        $process = $this->find($runId);

        if ($process === null) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_PROCESS_NOT_DEFINED, $runId));
        }

        $this->remove($runId);

        return $process;
    }
}
