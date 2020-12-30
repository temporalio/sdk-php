<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Common\Uuid;
use Temporal\Internal\Marshaller\Meta\Marshal;

class WorkflowExecution
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'ID')]
    public string $id;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'RunID')]
    public string $runId;

    /**
     * @param string|null $id
     * @param string|null $runId
     * @throws \Exception
     */
    public function __construct(string $id = null, string $runId = null)
    {
        $this->id = $id ?? Uuid::nil();
        $this->runId = $runId ?? Uuid::nil();
    }
}
