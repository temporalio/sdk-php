# Meta

> TODO: Adapt the documentation from Java as a business logic process 
> https://docs.temporal.io/docs/java-workflow-interface

## Factory

First you need to create a metadata reader object. To do this, you need to 
create a factory and call the `create()` method.

```php
use Temporal\Client\Meta\Factory;

$reader = (new Factory())->create();
```

You can use different annotation formats. To specify which one you need in 
the `Factory`, use special constants:

```php
use Temporal\Client\Meta\Factory;

$factory = new Factory();

// Prefer Native (PHP 8.0) metadata reader.
$native = $factory->create(Factory::PREFER_NATIVE);

// Prefer Doctrine reader.
$doctrine = $factory->create(Factory::PREFER_DOCTRINE);
```

In the case that you need to add your own reader, just add it to the factory's 
constructor:

```php
use Temporal\Client\Meta\Factory;

const MY_READER = Factory::CUSTOM_READER + 1; 
// Please note that the first reader's indices are occupied by builtin drivers,
// so you should use a "CUSTOM_READER" constant to indicate your own identifier.

$factory = new Factory([
    MY_READER => new MyResolver() // Instance of Temporal\Client\Meta\ResolverInterface
]);

// ...

$factory->create(MY_READER);
```
