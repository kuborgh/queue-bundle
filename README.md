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

### Step 3: Configure cron

The cronjob's purpose is to ensure, that the queue-runner is always running. This can be useful for server restarts, when the queue runner gets killed or when it is terminated due to errors. The cron interval is the maximum time, the queue may not run in worst-case, so 1h should be absolutely fine in most cases.
```crontab
0 * * * * cd <your-installation>; php app/console queue:runner > /dev/null 2>&1
```

When you are interested in the output of the queue runner, you can redirect it into a file
```crontab
0 * * * * (cd <your-installation>; echo `date` START; php app/console queue:runner; echo `date` END) >> logs/queue-runner.log 2>&1
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

Debugging / Monitoring
----------------------

To see, what the queue is doing at the moment, the best place to look is the database, or the log files.
On the terminal the following command is most useful to see the current processes
```bash
$ ps uxf
```

Logentries integration
----------------------

By using the the kuborgh/logentries-bundle, it is possible to send periodically stats of the queue to logentries. This 
allows you to set an alert, when the queue is hanging, or identifiy long-running jobs
 
1. Install and configure the kuborgh/logentries-bundle to have a logger service named `queue`.
```yml
kuborgh_logentries:
    logger:
        queue:
            log_set: MyHost
            log: Queue
```

2. Add a job to your crontab
```cron
# Report state to logentries.com
*/5 * * * * php app/console queue:logentries > /dev/null 2>&1
```
