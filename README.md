Queue Bundle
============

This is a queue implementation for symfony commands. 
It helps to asynchronously execute symfony commands, by writing then into a database and process them with a queue runner, spawned by a cron job.

It has the following features:
* Pure PHP implementation - No need for extra software on the server
* Cron-based run-detection - Check periodically if the queue still runs
* Stall-detection - Check by PID if the command still runs or has silently failed
* Short waitstate between jobs - No need to wait a minute until the cron is run again. Start jobs in < 10s.
* Symfony commands for monitoring and queueing
* MySQL database storage - This makes it easy for debugging insight and manipulation
* Configurable concurrency - Run more jobs in parallel, when needed
* Prioritization - 5 levels of priority to prefer important commands
* Duplicate check - Skip adding the same command twice to the queue, when the first run is still pending.

Installation
------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require kuborgh/queue-bundle
```

### Step 2: Enable the Bundle

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

Configuration
-------------

Following configuration variables exist an can be inserted into the config.yml of your project
```
kuborgh_queue:
    # How many queue jobs can be run parallel
    concurrency: 1

    # Remove successfull jobs from the queue after 3 days
    auto_cleanup: true

    # Path to console command to execute the jobs
    console_path: %kernel.root_dir%/console
```
