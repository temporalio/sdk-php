<?php


namespace Temporal\Exception\Failure;


class ChildWorkflowFailure extends TemporalFailure
{
//    private final long initiatedEventId;
//  private final long startedEventId;
//  private final String namespace;
//  private final RetryState retryState;
//  private final WorkflowExecution execution;
//  private final String workflowType;
//
//  public ChildWorkflowFailure(
//      long initiatedEventId,
//      long startedEventId,
//      String workflowType,
//      WorkflowExecution execution,
//      String namespace,
//      RetryState retryState,
//      Throwable cause) {
//    super(
//        getMessage(
//            execution, workflowType, initiatedEventId, startedEventId, namespace, retryState),
//        null,
//        cause);
//    this.execution = Objects.requireNonNull(execution);
//    this.workflowType = Objects.requireNonNull(workflowType);
//    this.initiatedEventId = initiatedEventId;
//    this.startedEventId = startedEventId;
//    this.namespace = namespace;
//    this.retryState = retryState;
//  }

public function getInitiatedEventId() {
    return initiatedEventId;
  }

  public function getStartedEventId() {
    return startedEventId;
  }

  public function getNamespace() {
    return namespace;
  }

  public function getRetryState() {
    return retryState;
  }

  public function getExecution() {
    return execution;
  }

  public function getWorkflowType() {
    return workflowType;
  }
//
//  public static String getMessage(
//    WorkflowExecution execution,
//      String workflowType,
//      long initiatedEventId,
//      long startedEventId,
//      String namespace,
//RetryState retryState) {
//        return "workflowId='"
//            + execution.getWorkflowId()
//            + '\''
//            + ", runId='"
//            + execution.getRunId()
//            + '\''
//            + ", workflowType='"
//            + workflowType
//            + '\''
//            + ", initiatedEventId="
//            + initiatedEventId
//            + ", startedEventId="
//            + startedEventId
//            + ", namespace='"
//            + namespace
//            + '\''
//            + ", retryState="
//            + retryState;
//    }
}
