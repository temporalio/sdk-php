<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use Temporal\Common\Uuid;
use Temporal\Exception\CancellationException;
use Temporal\Internal\ServiceContainer;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowContext;

/**
 * @internal CancellationScope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
class CancellationScope extends Scope
{
    /**
     * @var string
     */
    private string $id;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        WorkflowContext $ctx,
        ServiceContainer $services,
        callable $handler,
        array $args = []
    ) {
        $this->id = Uuid::v4();

        parent::__construct($ctx, $services, $handler, $args);

        $this->next();
    }

    /**
     * @return string
     */
    public function getRunId(): string
    {
        return $this->context->getRunId();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        // todo: get from parent
        return $this->id;
    }

    /**
     * @param mixed $result
     */
    protected function onComplete($result): void
    {
        // todo: trigger parent
        //
    }

    /**
     * @param \Throwable $e
     */
    protected function onException(\Throwable $e)
    {
        if ($e instanceof CancellationException) {
            $this->cancel();
        }
    }

    public function createScope(callable $handler, bool $detached): CancellationScopeInterface
    {
        // TODO: Implement createScope() method.
    }
}
