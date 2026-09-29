<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Workflow\GeneratedChildWorkflowId;
use Temporal\Worker\Transport\Command\Client\Request;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
final class ExecuteChildWorkflow extends Request
{
    public const NAME = 'ExecuteChildWorkflow';

    /** @var non-empty-string */
    private string $workflowType;

    private ?GeneratedChildWorkflowId $generatedWorkflowId = null;

    /**
     * @param non-empty-string $name Workflow name
     * @param RequestOptions $options
     */
    public function __construct(string $name, ValuesInterface $input, array $options, HeaderInterface $header)
    {
        $this->workflowType = $name;
        parent::__construct(self::NAME, ['name' => $name, 'options' => $options], $input, header: $header);
    }

    /**
     * @return non-empty-string
     */
    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }

    public function getOptions(): array
    {
        $options = $this->options;
        if ($this->generatedWorkflowId !== null) {
            $options['options']['WorkflowID'] = $this->generatedWorkflowId->getWorkflowId();
        }

        return $options;
    }

    public function getWorkflowId(): ?string
    {
        if ($this->generatedWorkflowId !== null) {
            return $this->generatedWorkflowId->getWorkflowId();
        }

        $options = $this->options['options'] ?? null;
        $workflowId = \is_array($options) ? $options['WorkflowID'] ?? null : null;

        return \is_string($workflowId) && $workflowId !== '' ? $workflowId : null;
    }

    public function getGeneratedWorkflowId(): ?GeneratedChildWorkflowId
    {
        return $this->generatedWorkflowId;
    }

    public function withGeneratedWorkflowId(GeneratedChildWorkflowId $workflowId): self
    {
        $clone = clone $this;
        $clone->generatedWorkflowId = $workflowId;

        return $clone;
    }
}
