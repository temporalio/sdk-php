<?php

namespace PHPSTORM_META {

    override(\Temporal\Workflow\WorkflowContextInterface::newActivityStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newActivityStub(), map([
        '' => type(0),
    ]));


    override(\Temporal\Workflow\WorkflowContextInterface::newChildWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Internal\Workflow\WorkflowContext::newChildWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newChildWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow\WorkflowContextInterface::newContinueAsNewStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newContinueAsNewStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow\WorkflowContextInterface::newExternalWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newExternalWorkflowStub(), map([
        '' => type(0),
    ]));



    // RPC
    override(\Temporal\Client\WorkflowClientInterface::newWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\WorkflowClient::newWorkflowStub(), map([
        '' => type(0),
    ]));
}
