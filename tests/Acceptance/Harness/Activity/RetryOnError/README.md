# Retrying activities on error

Failed activities can retry in a number of ways. This is configurable by retry policies that govern if and
how a failed activity may retry.

## Feature implementation

* Workflow executes activity with 5 max attempts and low backoff
* Activity errors every time with the attempt that failed
* Workflow waits on activity and re-bubbles its same error
* Confirm the right attempt error message is present