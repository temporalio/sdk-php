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
    private const ERROR_PROCESS_NOT_FOUND = 'Process #%s not found.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $runId
     * @param non-empty-string|null $error Error message if the process was not found.
     * @return Process
     */
    public function pull(string $runId, ?string $error = null): Process
    {
        $process = $this->find($runId) ?? throw new \InvalidArgumentException(
            $error ?? \sprintf(self::ERROR_PROCESS_NOT_FOUND, $runId),
        );

        $this->remove($runId);

        return $process;
    }
}
