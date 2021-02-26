<?php

namespace PHPSTORM_META {

    // -------------------------------------------------------------------------
    //  Worker
    // -------------------------------------------------------------------------

    override(\Temporal\Workflow\WorkflowContextInterface::newActivityStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Workflow::newActivityStub(), map([
        '' => type(0),
    ]));


    override(\Temporal\Workflow\WorkflowContextInterface::newChildWorkflowStub(), map([
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


    // -------------------------------------------------------------------------
    //  Client
    // -------------------------------------------------------------------------

    override(\Temporal\Client\WorkflowClientInterface::newWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Client\WorkflowClient::newWorkflowStub(), map([
        '' => type(0),
    ]));


    override(\Temporal\Client\WorkflowClientInterface::newRunningWorkflowStub(), map([
        '' => type(0),
    ]));

    override(\Temporal\Client\WorkflowClient::newRunningWorkflowStub(), map([
        '' => type(0),
    ]));
}
