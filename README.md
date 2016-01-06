Queue Bundle
============

This is a queue implementation for symfony commands. 
It helps to asynchronously execute symfony commands, by writing then into a database and process them with a queue runner, spawned by a cron job.

Installation
------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require kuborgh/queue-bundle
```

### Step 2: Import the config

Afterwards you need to import the configuration from the bundle into main config (app/config/config.yml). Add the following line
```
imports:
    - {resource: @QueueBundle/Resources/config/parameters.yml}
```
into your config.yml

### Step 3: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new Kuborgh\QueueBundle\QueueBundle(),
        );

        // ...
    }

    // ...
}
```
