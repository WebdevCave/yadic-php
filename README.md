# Yet another dependency injection container for PHP

![StyleCi](https://github.styleci.io/repos/816542238/shield)
[![Latest Stable Version](https://poser.pugx.org/webdevcave/yadic/v/stable?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![Latest Unstable Version](https://poser.pugx.org/webdevcave/yadic/v/unstable?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![Total Downloads](https://poser.pugx.org/webdevcave/yadic/downloads?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![License](https://poser.pugx.org/webdevcave/yadic/license?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![codecov](https://codecov.io/github/WebdevCave/yadic-php/graph/badge.svg?token=6GLECJQG16)](https://codecov.io/github/WebdevCave/yadic-php)

This is a simple to use, yet powerful service container that provides a seamless way to automate dependency injection
featuring auto-wiring and object hydration.

```bash
composer require webdevcave/yadic
```

Alternatively, you can clone the repository or download the source files directly and include them in your project.

## Usage

### Autowiring

```php
<?php

require_once __DIR__.'/vendor/autoload.php';

use Webdevcave\Yadic\Annotations\Provides;
use Webdevcave\Yadic\Annotations\Singleton;
use Webdevcave\Yadic\ServiceContainer;

interface StorageInterface
{
    public function store(mixed $data): bool;
}

#[Provides('storage')]
#[Provides(StorageInterface::class)]
#[Singleton]
class Storage implements StorageInterface
{
    public function store(mixed $data): bool
    {
        //store data...

        return true;
    }
}

class MyController
{
    public function __construct(
        private StorageInterface $storage
    ) {
    }

    public function save(): bool
    {
        return $this->storage->store('my data...');
    }
}

$container = new ServiceContainer();

//No need to do this in a real world application:
$container->addAlias(StorageInterface::class, Storage::class);

//Use this instead:
//$container->loadDefinitionsFromDirectory($directory, $namespace); //Loads annotations from classes declared in a PSR4 directory
//var_dump($container->get('storage')->store($data));

var_dump($container->get(MyController::class)->save()); //bool(true)
```

### Invoking a method ft. autowiring

```php
$arguments = ['nonInjectableArgument' => 'value']; //optional
$container->invoke([$instance, 'methodName'], $arguments);
```

### Hydration

```php
//Class declarations:

use Webdevcave\Yadic\Annotations\ArrayOf;

class Candidate
{
    public function __construct(
        public ?string $name = null,
        public ?int $age = null,
        #[ArrayOf(Skill::class)]
        public array $skills = []
    ) {
    }
}

class Skill
{
    public function __construct(
        public string $title,
    ) {
    }
}

// Hydration example 1:
$data = [
    'name'   => 'John Doe',
    'age'    => 25,
    'skills' => [
        ['title' => 'PHP'],
        ['title' => 'Java'],
        ['title' => 'Rust'],
        ['title' => 'React'],
    ],
];
$instance = $container->hydrate(Candidate::class, $data);

//Results output
/*
print_r($instance);
This test printed output: 
Candidate Object
(
    [name] => John Doe
    [age] => 25
    [skills] => Array
        (
            [0] => Skill Object
                (
                    [title] => PHP
                )

            [1] => Skill Object
                (
                    [title] => Java
                )

            [2] => Skill Object
                (
                    [title] => Rust
                )

            [3] => Skill Object
                (
                    [title] => React
                )

        )

)
 */

// Hydration example 2:
$data = [
    [
        'name' => 'Foo',
        //...
    ],
    [
        'name' => 'Bar',
        //...
    ]
];
$instances = $container->hydrate(Candidate::class, $data);
//Results output
/*
print_r($instances);
This test printed output: 
Array
(
    [0] => Candidate Object
        (
            [name] => Foo
            //...
        )

    [1] => Candidate Object
        (
            [name] => Bar
            //...
        )
)
 */
```

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements,
please open an issue or a pull request on GitHub.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
