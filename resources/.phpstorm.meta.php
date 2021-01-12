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
    override(\Temporal\Client\WorkflowClientInterface::newWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Client\WorkflowClient::newWorkflowStub(), map([
        '' => type(0),
    ]));
}
