# Meta

> TODO: Adapt the documentation from Java as a business logic process 
> https://docs.temporal.io/docs/java-workflow-interface

## Factory

First you need to create a metadata reader object. To do this, you need to 
create a factory and call the `create()` method.

```php
use Temporal\Client\Internal\Meta\Factory;

$reader = (new Factory())->create();
```

You can use different annotation formats. To specify which one you need in 
the `Factory`, use special constants:

- `Factory::PREFER_SELECTIVE`
- `Factory::PREFER_NATIVE`
- `Factory::PREFER_DOCTRINE`

Each of the drivers has its own peculiarity.

### Native Reader

The Native Reader only supports PHP 8.0+ attributes. All metadata using other formats will be ignored.

```php
<?php

use Temporal\Client\Internal\Meta\Factory;

$reader = (new Factory())->create(Factory::PREFER_NATIVE);

var_dump($reader);

//
// Expected Result: 
// > Temporal\Client\Meta\Native\NativeReader {#9}
//
```

Reading of attributes can proceed as follows:

```php
#[ExampleAttribute]
class Example {}

$attributes = $reader->getClassMetadata(
    new \ReflectionClass(Example::class)
);

var_dump($attributes);

//
// Expected Result:
// > array(1) { ExampleAttribute }
//
```

> Please note that if the driver not supported (PHP version below 8.0), the first 
> available driver version will be selected.

### Doctrine Reader

To use this type of metadata make sure you include the `doctrine/annotations` dependency:

- `$ composer require doctrine/annotations`

This reader only supports Doctrine annotations. All metadata using other formats will be ignored.

```php
<?php

use Temporal\Client\Internal\Meta\Factory;

$reader = (new Factory())->create(Factory::PREFER_DOCTRINE);

var_dump($reader);

//
// Expected Result: 
// > Temporal\Client\Meta\Doctrine\DoctrineReader {#9}
//
```

Reading of annotations can proceed as follows:

```php
/** @ExampleAnnotation */
class Example {}

$annotations = $reader->getClassMetadata(
    new \ReflectionClass(Example::class)
);

var_dump($annotations);

//
// Expected Result:
// > array(1) { ExampleAnnotation }
//
```

> Please note that if the driver not supported ("doctrine/annotations" dependency not installed), the first
> available driver version will be selected.

### Selective Reader

This reader allows you to read annotations/attributes of any format. This driver is only available if both the 
Doctrine reader and the Native reader are supported by your application and may be required when the project 
does not know in advance what format of attributes/annotations will be used, or when refactoring.

```php
<?php

use Temporal\Client\Internal\Meta\Factory;

$reader = (new Factory())->create(Factory::PREFER_SELECTIVE);

var_dump($reader);

//
// Expected Result: 
// > Temporal\Client\Meta\Selective\SelectiveReader {#9}
//
```

Reading of attributes/annotations can proceed as follows:

```php
/** @ExampleAnnotation */
class Example 
{
    #[ExampleAnnotation]
    public function test() {}
}

$annotations = $reader->getClassMetadata(
    new \ReflectionClass(Example::class)
);

var_dump($annotations);

//
// Expected Result:
// > array(1) { ExampleAnnotation }
//

$annotations = $reader->getMethodMetadata(
    new \ReflectionMethod(Example::class, 'test')
);

var_dump($annotations);

//
// Expected Result:
// > array(1) { ExampleAnnotation }
//
```

Please note that this type of reader selects one attribute format, ignoring the rest. This means that when using two 
different formats at the same time, the attributes of PHP 8 will be read first:

```php
/** @DoctrineAnnotation */
#[NativeAttribute]
class Example {}

$result = $reader->getClassMetadata(new \ReflectionClass(Example::class));

var_dump($result);

//
// Expected Result:
// > array(1) { NativeAttribute }
//
```

### Custom Reader

In the case that you need to add your own reader, just add it to the factory's 
constructor:

```php
use Temporal\Client\Internal\Meta\Factory;

const MY_READER = Factory::CUSTOM_READER + 1; 

// Please note that the first reader's indices are occupied by builtin drivers,
// so you should use a "CUSTOM_READER" constant to indicate your own identifier.

$factory = new Factory([
    MY_READER => new MyResolver() // Instance of Temporal\Client\Meta\ResolverInterface
]);

// ...

$factory->create(MY_READER);
```
