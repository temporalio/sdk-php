# Workflows

A workflow is an implementation of coordination logic. The PHP SDK allows you to write the logic for coordinating 
the workflow in the form of a familiar PHP code that uses [coroutines] and [promises]. The PHP SDK takes care of the
communication between the worker service and the temporary service and ensures that state is maintained between events
even in the event of worker failures. Moreover, any specific execution is not tied to a specific working machine. 
Different steps of the coordination logic can end up being executed on different worker instances, with the 
framework ensuring that the required state is recreated on the worker performing the step.

## Overview

The sample code below shows a simple implementation of a Workflow that executes one [Activity]. 
The Workflow also passes the sole parameter it receives as part of its initialization as a 
parameter to the Activity.

```php
namespace Example;

use Temporal\Client\Internal\Workflow\WorkflowContextInterface;use Temporal\Client\Workflow\WorkflowMethod;

final class FileProcessingWorkflow
{
    /**
     * Local name of the file.
     */
    private ?string $localName = null;
    
    /**
     * Processed local name of the file.
     */
    private ?string $processedName = null;

    /**
     * An example of a workflow with processing a file.
     * 
     * Note that the processing method contains the "WorkflowMethod" 
     * attribute which indicates that this method can be called by 
     * the Temporal server.
     */
    #[WorkflowMethod]
    public function process(WorkflowContextInterface $ctx, array $arguments)
    {
        try {
            // We call some action (activity) that downloads a file
            // from the bucket (S3 API specific) by its name.
            //
            // As a result, we get the local name of this file.
            $this->localName = yield $ctx->executeActivity('download', [
                $arguments['sourceBucket'],
                $arguments['sourceName']
            ]);
            
            // We do any transformations on this file and get the new file name.
            $this->processedName = yield $ctx->executeActivity('processFile', [
                $this->localName
            ]);

            // Uploading the processed file to the server
            // by its bucket and name.
            yield $ctx->executeActivity('upload', [
                $arguments['targetBucket'],
                $arguments['targetName'],
                $this->processedName
            ]);
        } finally {
            if ($this->localName !== null) {
                yield $ctx->executeActivity('deleteLocalFile', [$this->localName]);
            }

            if ($this->processedName !== null) {
                yield $ctx->executeActivity('deleteLocalFile', [$this->processedName]);
            }
        }
    }
}
```

In this example, we use an important feature of the Temporal - an [Activity] call. Each Activity is an action that 
can create side effects, but they cannot be inside the Workflow.

You can notice that each step was accompanied by the keyword `yield`. Using coroutines temporarily stops the 
Workflow execution and waits for the result of the Activity. Thus, we can not expect an execution result in the 
event that it is not required:

```php
#[WorkflowMethod]
public function process(WorkflowContextInterface $ctx, array $arguments)
{
    $ctx->executeActivity('notify', ['Some kind of Workflow was started']);
    
    $result = yield $ctx->executeActivity('doSomethingImportant');
}
```

In addition, each call of the Activity returns the Promise (see [promises]) with which we can interact in the case 
that the coroutines for some reason do not suit us:

```php
#[WorkflowMethod]
public function process(WorkflowContextInterface $ctx, array $arguments)
{
    $ctx->executeActivity('notify', ['Some kind of Workflow was started'])
        ->then(fn($result) => 
            $ctx->executeActivity('notify', ['The notification was sent successfully'])
        )
        ->otherwise(fn($error) => 
            $ctx->executeActivity('notify', ['Something went wrong: ' . $error])
        )
    ;
    
    yield $ctx->executeActivity('doSomethingImportant');
}
```

### Calling Activities Asynchronously

Sometimes Workflows need to perform certain operations in parallel. In this case, you can use special functions 
that allow you to manage a set of "parallel" promises.

Thus, to download several files from the server (from our first example), we can use such an `all()` helper 
method which is an implementation of the popular `Promise.all()` function:

```php
namespace Example;

use React\Promise\PromiseInterface;use Temporal\Client\Internal\Workflow\WorkflowContextInterface;use Temporal\Client\Workflow\WorkflowMethod;

final class FileProcessingWorkflow
{
    /** @var array<string> */
    private array $localNames;
    
    #[WorkflowMethod]
    public function process(WorkflowContextInterface $ctx, array $arguments)
    {
        // A function that returns a Promise for each passed "$name" argument.
        $map = static fn(string $name): PromiseInterface => 
            $ctx->executeActivity('download', [$arguments['sourceBucket'], $name])
        ;
 
        $this->localNames = yield $ctx->all(
            array_map($map, $arguments['sourceNames'])
        );
        
        // etc..
    }
}
```



[coroutines]: https://www.php.net/manual/en/language.generators.overview.php
[promises]: https://promisesaplus.com/
[Activity]: activities.md
