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

2. Add `bootstrap.php` to your `phpunit.xml`:
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
>
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

Under the hood RoadRunner is started with `rr serve` command. You can specify your own command in `bootstrap.php`:
```php
$environment->start('./rr serve -c .rr.test.yaml -w tests');
```

The snippet above will start Temporal test server and RoadRunner with `.rr.test.yaml` config and `tests` working
directory. Having a separate RoadRunner config file for tests can be useful to mock you activities. For 
example, you can create a separate *worker* that registers activity implementations mocks:

```yaml
# test/.rr.test.yaml
server:
    command: "php worker.test.php"
```

And within the worker you register your workflows and mock activities:

```php
// worker.test.php 
$factory = WorkerFactory::create();

$worker = $factory->newWorker();
$worker->registerWorkflowTypes(MyWorkflow::class);
$worker->registerActivity(MyActvivityMock::class);
$factory->run();
```




