<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Workflow\ReturnType;

final class WorkflowPrototype extends Prototype
{
    /**
     * @var array<non-empty-string, QueryDefinition>
     */
    private array $queryHandlers = [];

    /**
     * @var array<non-empty-string, SignalDefinition>
     */
    private array $signalHandlers = [];

    /**
     * @var array<non-empty-string, UpdateDefinition>
     */
    private array $updateHandlers = [];

    /**
     * @var array<non-empty-string, \ReflectionFunctionAbstract>
     */
    private array $updateValidators = [];

    private ?CronSchedule $cronSchedule = null;
    private ?MethodRetry $methodRetry = null;
    private ?ReturnType $returnType = null;
    private bool $hasInitializer = false;

    public function hasInitializer(): bool
    {
        return $this->hasInitializer;
    }

    public function setHasInitializer(bool $hasInitializer): void
    {
        $this->hasInitializer = $hasInitializer;
    }

    public function getCronSchedule(): ?CronSchedule
    {
        return $this->cronSchedule;
    }

    public function setCronSchedule(?CronSchedule $attribute): void
    {
        $this->cronSchedule = $attribute;
    }

    public function getMethodRetry(): ?MethodRetry
    {
        return $this->methodRetry;
    }

    public function setMethodRetry(?MethodRetry $attribute): void
    {
        $this->methodRetry = $attribute;
    }

    public function getReturnType(): ?ReturnType
    {
        return $this->returnType;
    }

    public function setReturnType(?ReturnType $attribute): void
    {
        $this->returnType = $attribute;
    }

    public function addQueryHandler(QueryDefinition $definition): void
    {
        $this->queryHandlers[$definition->name] = $definition;
    }

    /**
     * @return array<non-empty-string, QueryDefinition>
     */
    public function getQueryHandlers(): array
    {
        return $this->queryHandlers;
    }

    public function addSignalHandler(SignalDefinition $definition): void
    {
        $this->signalHandlers[$definition->name] = $definition;
    }

    /**
     * @return array<non-empty-string, SignalDefinition>
     */
    public function getSignalHandlers(): array
    {
        return $this->signalHandlers;
    }

    public function addUpdateHandler(UpdateDefinition $definition): void
    {
        $this->updateHandlers[$definition->name] = $definition;
    }

    /**
     * @param non-empty-string $name
     */
    public function addValidateUpdateHandler(string $name, \ReflectionFunctionAbstract $fun): void
    {
        $this->updateValidators[$name] = $fun;
    }

    /**
     * @return array<non-empty-string, UpdateDefinition>
     */
    public function getUpdateHandlers(): array
    {
        return $this->updateHandlers;
    }

    /**
     * @return array<non-empty-string, \ReflectionFunctionAbstract>
     */
    public function getValidateUpdateHandlers(): array
    {
        return $this->updateValidators;
    }
}
