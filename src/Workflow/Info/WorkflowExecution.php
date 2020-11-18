<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Info;

use Temporal\Client\Internal\Support\Uuid4;

final class WorkflowExecution
{
    /**
     * @readonly
     * @var string
     */
    public string $id;

    /**
     * @readonly
     * @var string
     */
    public string $runId;

    /**
     * @param string|null $id
     * @param string|null $runId
     * @throws \Exception
     */
    public function __construct(string $id = null, string $runId = null)
    {
        $this->id = $id ?? Uuid4::create();
        $this->runId = $runId ?? Uuid4::create();
    }

    /**
     * TODO throw exception in case of incorrect data
     *
     * @param array $data
     * @return static
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self($data['ID'], $data['RunID']);
    }
}
