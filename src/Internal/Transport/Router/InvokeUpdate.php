<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\Command\UpdateResponse;
use Temporal\Workflow\Update\UpdateResult;

final class InvokeUpdate extends WorkflowProcessAwareRoute
{
    private const ERROR_HANDLER_NOT_FOUND = 'unknown updateName %s. KnownUpdateNames=[%s]';

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $isReplay = (bool)($request->getOptions()['replay'] ?? false);
        $name = $request->getOptions()['name'];
        $process = $this->findProcessOrFail($request->getID());
        $context = $process->getContext();
        $instance = $process->getWorkflowInstance();
        $handler = $this->getUpdateHandler($instance, $name);
        $updateId = $request->getOptions()['updateId'];

        /** @psalm-suppress InaccessibleProperty */
        $context->getInfo()->historyLength = $request->getHistoryLength();

        $input = new UpdateInput(
            signalName: $name,
            info: $context->getInfo(),
            arguments: $request->getPayloads(),
            header: $request->getHeader(),
        );

        // Validation
        if ($isReplay) {
            $resolver->resolve(new UpdateResult(
                command: UpdateResult::COMMAND_VALIDATED,
            ));
        } else {
            $validator = $instance->findValidateUpdateHandler($name);

            try {
                // Validation will be passed if no validation handler is found
                if ($validator !== null) {
                    $validator($input);
                }

                $context->getClient()->send(new UpdateResponse(
                    command: UpdateResult::COMMAND_VALIDATED,
                    values: null,
                    failure: null,
                    updateId: $updateId,
                ));
            } catch (\Throwable $e) {
                $resolver->reject(new UpdateResult(
                    command: UpdateResult::COMMAND_VALIDATED,
                    failure: $e,
                ));
                return;
            }
        }

        // There validation is passed

        /** @var PromiseInterface $promise */
        $promise = $handler($input);
        $promise->then(
            static function (mixed $value) use ($updateId, $context, $resolver): void {
                $resolver->resolve(new UpdateResult(
                    command: UpdateResult::COMMAND_COMPLETED,
                    result: EncodedValues::fromValues([$value]),
                ));
            },
            static function (\Throwable $err) use ($updateId, $context, $resolver): void {
                $resolver->resolve(new UpdateResult(
                    command: UpdateResult::COMMAND_COMPLETED,
                    failure: $err,
                ));
            },
        );
    }

    /**
     * @param non-empty-string $name
     */
    private function getUpdateHandler(WorkflowInstanceInterface $instance, string $name): \Closure
    {
        $handler = $instance->findUpdateHandler($name);

        if ($handler === null) {
            $available = \implode(' ', $instance->getUpdateHandlerNames());

            throw new \LogicException(\sprintf(self::ERROR_HANDLER_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
