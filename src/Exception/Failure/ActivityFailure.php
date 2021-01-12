<?php


namespace Temporal\Exception\Failure;


class ActivityFailure extends TemporalFailure
{

    /*
     *  private final long scheduledEventId;
  private final long startedEventId;
  private final String activityType;
  private final String activityId;
  private final String identity;
  private final RetryState retryState;

  public ActivityFailure(
      long scheduledEventId,
      long startedEventId,
      String activityType,
      String activityId,
      RetryState retryState,
      String identity,
      Throwable cause) {
    super(
        getMessage(
            scheduledEventId, startedEventId, activityType, activityId, retryState, identity),
        null,
        cause);
    this.scheduledEventId = scheduledEventId;
    this.startedEventId = startedEventId;
    this.activityType = activityType;
    this.activityId = activityId;
    this.identity = identity;
    this.retryState = retryState;
  }
*/
    public function getScheduledEventId()
    {
        return scheduledEventId;
    }

    public function getStartedEventId()
    {
        return startedEventId;
    }

    public function getActivityType()
    {
        return activityType;
    }

    public function getActivityId()
    {
        return activityId;
    }

    public function getIdentity()
    {
        return identity;
    }

    public function getRetryState()
    {
        return retryState;
    }
    /*
      public static String getMessage(
          long scheduledEventId,
          long startedEventId,
          String activityType,
          String activityId,
          RetryState retryState,
          String identity) {
        return "scheduledEventId="
            + scheduledEventId
            + ", startedEventId="
            + startedEventId
            + ", activityType='"
            + activityType
            + '\''
            + (activityId == null ? "" : ", activityId='" + activityId + '\'')
            + ", identity='"
            + identity
            + '\''
            + ", retryState="
            + retryState;
      }
         */
}
