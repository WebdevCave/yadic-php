# Yet another dependency injection container for PHP

![StyleCi](https://github.styleci.io/repos/816542238/shield)
[![Latest Stable Version](https://poser.pugx.org/webdevcave/yadic/v/stable?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![Latest Unstable Version](https://poser.pugx.org/webdevcave/yadic/v/unstable?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![Total Downloads](https://poser.pugx.org/webdevcave/yadic/downloads?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)
[![License](https://poser.pugx.org/webdevcave/yadic/license?format=flat-square)](https://packagist.org/packages/webdevcave/yadic)

This is a simple to use, yet powerful service container that provides a seamless way to automate dependency injection
with auto-wiring.

```bash
composer require webdevcave/yadic
```

Alternatively, you can clone the repository or download the source files directly and include them in your project.

## Usage

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

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements,
please open an issue or a pull request on GitHub.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
