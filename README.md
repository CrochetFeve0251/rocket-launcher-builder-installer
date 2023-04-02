# rocket-launcher-builder-installer
Install libraries from the Rocket Launcher Framework.

## Install
To install run the following command : `composer require crochetfeve0251/rocket-launcher-logger-take-off`

Then you need to add the provider `RocketLauncherBuilderInstaller\ServiceProvider` to the `/bin/generator` file:

```php
#!/usr/bin/php
<?php

use RocketLauncherBuilder\AppBuilder;

require_once __DIR__ . '/../vendor/autoload.php';

AppBuilder::init(__DIR__ . '/../', [
        \RocketLauncherTakeOff\ServiceProvider::class,
        \RocketLauncherBuilderInstaller\ServiceProvider::class,
]);
```

## Create a library compatible

To make a library compatible you need to add the following content:
```json
"extra": {
    "rocket-launcher": {
      "provider" : "MyProvider",
      "command": "my-command",
      "install": true,
      "clean": true,
      "libraries": {
        "owner/library": "^0.0.1"
      }
    }
  }
```

| Name      | Type    | Example                       | Description                                                  |
|-----------|---------|-------------------------------|--------------------------------------------------------------|
| provider  | string  | `MyLibrary\MyProvider`        | The service provider from the library if there is one        |
| command   | string  | `my-command`                  | The command to install the library if there is one           |
| install   | boolean | `true`                        | Should the command be executed or just displayed to the user |
| clean     | boolean | `true`                        | Should the library be removed after the installation         |
| libraries | array   | `{"owner/library": "^0.0.1"}` | Libraries to install into dependencies                       |

