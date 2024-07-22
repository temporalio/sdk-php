# Activity cancellation - Try Cancel mode
Activities may be cancelled in three different ways, this feature spec covers the
Try Cancel mode.

Each feature workflow in this folder should start an activity and cancel it
using the Try Cancel mode. The implementation should demonstrate that the activity
keeps receives a cancel request after the workflow has issued it, but the workflow
immediately should proceed with the activity result being cancelled. 

## Detailed spec

* When the SDK issues the activity cancel request command, server will write an
  activity cancel requested event to history
* The workflow immediately resolves the activity with its result being cancelled
* Server will notify the activity cancellation has been requested via a response
  to activity heartbeating
* The activity may ignore the cancellation request if it explicitly chooses to

## Feature implementation

* Execute activity that heartbeats and checks cancellation
  * If a minute passes without cancellation, send workflow a signal that it timed out
  * If cancellation is received, send workflow a signal that it was cancelled
* Cancel activity and confirm cancellation error is returned
* Check in the workflow that the signal sent from the activity is showing it was cancelled