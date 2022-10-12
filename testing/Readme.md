## Testing framework

### Quick start
1. Create `bootstrap.php` in `tests` folder with the following contents:
```php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Temporal\Testing\Environment;

$environment = Environment::create();
$environment->start();
register_shutdown_function(fn () => $environment->stop());
```

`$environment->start();` is a shortcut to run Temporal test server and RoadRunner worker.
You can start them separately if you need to customize the configuration:

```php
$this->startTemporalTestServer(); // starts Temporal server
$this->startRoadRunner(); // starts RoadRunner worker
```

So if, for example, you only need roadrunner worker and not the server you can
set condition to start it only if `RUN_TEMPORAL_TEST_SERVER` environment variable is present:

```php
$environment = Environment::create();

if (getenv('RUN_TEMPORAL_TEST_SERVER') !== false) {
    $this->startTemporalTestServer();
}

$environment->startRoadRunner('./rr serve -c .rr.silent.yaml -w tests');
register_shutdown_function(fn() => $environment->stop());
```

2. Add environment variable and `bootstrap.php` to your `phpunit.xml`:

```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
>
    <php>
        <env name="TEMPORAL_ADDRESS" value="127.0.0.1:7233" />
    </php>
</phpunit>
```

3. Add test server executable to `.gitignore`:
```gitignore
temporal-test-server
```

### How it works
For testing workflows there is no need to run a full Temporal server (with storage and ui interface).
Instead, we can use a light-weight test server.

The code in `bootstrap.php` will start/stop (and download if it doesn't exist) Temporal test 
server and RoadRunner for every phpunit run. Test server runs as a regular server on 7233 port. 
Thus, if you use default connection settings, there is no need to change them.

Under the hood RoadRunner is started with `rr serve` command. So make sure you have the binary.

You can specify your own command in `bootstrap.php`:
```php
$environment->start('./rr serve -c .rr.test.yaml -w tests');
```

The snippet above will start Temporal test server and RoadRunner with `.rr.test.yaml` config and `tests` working
directory. Having a separate RoadRunner config file for tests can be useful for activities mocking:

```yaml
# tests/.rr.test.yaml
server:
    command: "php worker.test.php"

kv:
    test:
        driver: memory
        config:
            interval: 10

```

And within the worker you register your workflows and activities:

```php
// worker.test.php 
use Temporal\Testing\WorkerFactory;

$factory = WorkerFactory::create();

$worker = $factory->newWorker();
$worker->registerWorkflowTypes(MyWorkflow::class);
$worker->registerActivity(MyActivity::class);
$factory->run();
```

### Time management 
By default, the test server starts with `--enable-time-skipping` option. It means that if the 
workflow has a timer, the server doesn't wait for it and continues immediately. To change
this behaviour you can use `TestService` class:

```php
$testService = TestService::create('localhost:7233');
$testService->lockTimeSkipping();

// ...
$testService->unlockTimeSkipping();
```

Class `TestService` communicates with a test server and provides method for "time management". Time skipping 
can be switched on/off with `unlockTimeSkipping()` and `lockTimeSkipping()` method. 

In case you need to emulate some "waiting" on a test server, you can use `sleep(int secods)` or `sleepUntil(int $timestamp)` methods.

Current server time can be retrieved with `getCurrentTime(): Carbon` method.

For convenience if you don't want to skip time in the whole `TestCase` class use `WithoutTimeSkipping`: 

```php
final class MyWorkflowTest extends TestCase 
{
    use WithoutTimeSkipping;
}
```

### Mocking activities

We consider activities as implementation details of workflows. Thus, we don't want to unit test them when
testing workflows. So, we can mock them in order to unit test different flows of the workflow. 

#### RoadRunner config

Under the hood activity mocking uses [RoarRunner Key-Value storage](https://github.com/spiral/roadrunner-kv), so you need to
add the following lines to your `tests/.rr.test.yaml` for testing:

```yaml
# tests/.rr.test.yaml
kv:
  test:
    driver: memory
    config:
        interval: 10
```


Notice, that if you want to have ability to mock activities you should use `WorkerFactory` from `Temporal\Testing` namespace
in your PHP worker:

```php
// worker.test.php 
use Temporal\Testing\WorkerFactory;

$factory = WorkerFactory::create();
$worker = $factory->newWorker();

$worker->registerWorkflowTypes(MyWorkflow::class);
$worker->registerActivity(MyActivity::class);
$factory->run();
```

Then, in your tests to mock an activity use `ActivityMocker` class. Assume we have the following activity:

```php
#[ActivityInterface(prefix: "SimpleActivity.")]
interface SimpleActivityInterface
{
    #[ActivityMethod('doSomething')]
    public function doSomething(string $input): string;
```

To mock it in the test you can do this:

```php
final class SimpleWorkflowTestCase extends TestCase
{
    private WorkflowClient $workflowClient;
    private ActivityMocker $activityMocks;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(ServiceClient::create('localhost:7233'));
        $this->activityMocks = new ActivityMocker();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->activityMocks->clear();
        parent::tearDown();
    }

    public function testWorkflowReturnsUpperCasedInput(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.doSomething', 'world');
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');
        $this->assertSame('world', $run->getResult('string'));
    }
}
```

In the test case above we:
1. Instantiate instance of `ActivityMocker` class in `setUp()` method of the test.
2. Don't forget to clear the cache after each test in `tearDown()`.
3. Mock an activity call to return a string `world`.

To mock a failure use `expectFailure()` method:

```php
$this->activityMocks->expectFailure('SimpleActivity.echo', new \LogicException('something went wrong'));
```

### Troubleshooting

#### Error starting Temporal server: `sh: exec: line 0: ./temporal-test-server: not found`

On `alpine`-based image *not found* might be caused by dynamic link failure.
Because `temporal-test-server` is
[built against `glibc`](https://github.com/temporalio/sdk-java/blob/master/temporal-test-server/build.gradle#L128)
and `alpine` uses [musl](https://musl.libc.org/) libc library.

To fix this you need to install one the glibc compatibility packages, for example `gcompat`.

```bash
apk add gcompat
```

> More info could be found in this [stackoverflow answer](https://stackoverflow.com/a/66974607/2457191)
