# Basic activity
The most basic workflow which just runs an activity and returns its result.
Importantly, without setting a workflow execution timeout.


# Detailed spec
It's important that the workflow execution timeout is not set here, because server will propagate that to all un-set
activity timeouts. We had a bug where TS would crash (after proto changes from gogo to google) because it was expecting
timeouts to be set to zero rather than null.