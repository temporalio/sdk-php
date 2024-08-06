<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Activity;

use React\Promise\PromiseInterface;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\DataConverter\Bytes;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\DTO\User;
use Temporal\Tests\DTO\WithEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\ScalarEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\SimpleEnum;

#[ActivityInterface(prefix: "SimpleActivity.")]
class SimpleActivity
{
    #[ActivityMethod]
    public function echo(
        string $input
    ): string {
        return strtoupper($input);
    }

    #[ActivityMethod]
    public function prefix(
        string $prefix,
        string $input
    ): string {
        if ($input === 'error') {
            throw new \Error('activity error');
        }

        return $prefix . $input;
    }

    #[ActivityMethod]
    public function lower(
        string $input
    ): string {
        return strtolower($input);
    }

    #[ActivityMethod]
    public function greet(
        User $user
    ): Message {
        return new Message(sprintf("Hello %s <%s>", $user->name, $user->email));
    }

    #[ActivityMethod]
    public function slow(
        string $input
    ): string {
        sleep(2);

        return strtolower($input);
    }

    #[ActivityMethod]
    public function md5(
        Bytes $input
    ): string {
        return md5($input->getData());
    }

    #[ActivityMethod]
    public function header(): array
    {
        return \iterator_to_array(Activity::getCurrentContext()->getHeader());
    }

    #[ActivityMethod]
    public function external()
    {
        Activity::doNotCompleteOnReturn();
        file_put_contents('runtime/taskToken', Activity::getInfo()->taskToken);
        file_put_contents(
            'runtime/activityId',
            json_encode(
                [
                    'id' => Activity::getInfo()->workflowExecution->getID(),
                    'runId' => Activity::getInfo()->workflowExecution->getRunID(),
                    'activityId' => Activity::getInfo()->id
                ]
            )
        );
    }

    public function updateRunID(WorkflowExecution $e): WorkflowExecution
    {
        $e->setRunId('updated');
        return $e;
    }

    #[ActivityMethod]
    public function fail()
    {
        throw new \Error("failed activity");
    }

    #[ActivityMethod]
    public function simpleEnum(SimpleEnum $enum): SimpleEnum
    {
        return $enum;
    }

    #[ActivityMethod]
    public function scalarEnum(ScalarEnum $enum): ScalarEnum
    {
        return $enum;
    }

    #[ActivityMethod('arrayOfObjects')]
    public function arrayOfObjects(
        $user
    ): array {
        return [
            new Message(sprintf("Hello %s", strtolower($user))),
            new Message(sprintf("Hello %s", strtoupper($user))),
        ];
    }

    #[ActivityMethod]
    public function simpleEnumDto(WithEnum $dto): WithEnum
    {
        return $dto;
    }

    #[ActivityMethod]
    public function namedArguments(
        string $input,
        bool $optionalBool = false,
        ?string $optionalNullableString = null,
    ): array {
        return [
            'input' => $input,
            'optionalBool' => $optionalBool,
            'optionalNullableString' => $optionalNullableString,
        ];
    }

    /**
     * @return PromiseInterface<null>
     */
    #[ActivityMethod]
    public function empty(): void
    {
    }
}
