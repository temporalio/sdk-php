<?php

namespace PHPSTORM_META {

    override(\Temporal\Workflow\WorkflowContextInterface::newActivityStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow\WorkflowContext::newActivityStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newActivityStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow\WorkflowContextInterface::newChildWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow\WorkflowContext::newChildWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newChildWorkflowStub(), map([
        '' => type(0),
    ]));


    // RPC
    override(\Temporal\Client\ClientInterface::newWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Client\Client::newWorkflowStub(), map([
        '' => type(0),
    ]));
}
