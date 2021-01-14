<?php

namespace Temporal\Tests\Activity;

use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\DataConverter\Bytes;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\DTO\User;

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
    public function external()
    {
        Activity::doNotCompleteOnReturn();
        file_put_contents('taskToken', Activity::getInfo()->taskToken);
        file_put_contents(
            'activityId',
            json_encode(
                [
                    'id' => Activity::getInfo()->workflowExecution->id,
                    'runId' => Activity::getInfo()->workflowExecution->runId,
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
}
