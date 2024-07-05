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

final class InvokeUpdate extends WorkflowProcessAwareRoute
{
    private const ERROR_HANDLER_NOT_FOUND = 'unknown update method %s. KnownUpdateNames=[%s]';

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request, array $headers, Deferred $resolver): void
    {
        $process = $this->findProcessOrFail($request->getID());
        $context = $process->getContext();
        $updateId = $request->getOptions()['updateId'];
        // Update requests don't require a response
        $resolver->promise()->cancel();

        try {
            $instance = $process->getWorkflowInstance();
            /** @var non-empty-string $name */
            $name = $request->getOptions()['name'];
            $handler = $this->getUpdateHandler($instance, $name);
            /** @psalm-suppress InaccessibleProperty */
            $context->getInfo()->historyLength = $request->getHistoryLength();

            $input = new UpdateInput(
                updateName: $name,
                info: $context->getInfo(),
                arguments: $request->getPayloads(),
                header: $request->getHeader(),
            );

            // Validation

            $isReplay = (bool)($request->getOptions()['replay'] ?? false);
            if ($isReplay) {
                // On replay, we don't need to execute validation handlers
                $context->getClient()->send(new UpdateResponse(
                    command: UpdateResponse::COMMAND_VALIDATED,
                    values: null,
                    failure: null,
                    updateId: $updateId,
                ));
            } else {
                $validator = $instance->findValidateUpdateHandler($name);

                // Validation will be passed if no validation handler is found
                if ($validator !== null) {
                    $validator($input);
                }

                $context->getClient()->send(new UpdateResponse(
                    command: UpdateResponse::COMMAND_VALIDATED,
                    values: null,
                    failure: null,
                    updateId: $updateId,
                ));
            }
        } catch (\Throwable $e) {
            $context->getClient()->send(
                new UpdateResponse(
                    command: UpdateResponse::COMMAND_VALIDATED,
                    values: null,
                    failure: $e,
                    updateId: $updateId,
                )
            );
            return;
        }

        // Validation has passed

        $deferred = new Deferred();
        $deferred->promise()->then(
            static function (mixed $value) use ($updateId, $context): void {
                $context->getClient()->send(new UpdateResponse(
                    command: UpdateResponse::COMMAND_COMPLETED,
                    values: EncodedValues::fromValues([$value]),
                    failure: null,
                    updateId: $updateId,
                ));
            },
            static function (\Throwable $err) use ($updateId, $context): void {
                $context->getClient()->send(new UpdateResponse(
                    command: UpdateResponse::COMMAND_COMPLETED,
                    values: null,
                    failure: $err,
                    updateId: $updateId,
                ));
            },
        );

        $handler($input, $deferred);
    }

    /**
     * @param non-empty-string $name
     * @return \Closure(UpdateInput, Deferred): PromiseInterface
     */
    private function getUpdateHandler(WorkflowInstanceInterface $instance, string $name): \Closure
    {
        return $instance->findUpdateHandler($name) ?? throw new \LogicException(
            \sprintf(self::ERROR_HANDLER_NOT_FOUND, $name, \implode(' ', $instance->getUpdateHandlerNames()))
        );
    }
}
