Messenger & asynchronous execution
==================================

Processes launched from the UI, from the [HTTP API](06-http_api.md) (unless `queue` is `false`) or from the
[scheduler](05-scheduler.md) are never executed by the web request: they are sent to a
[Symfony Messenger](https://symfony.com/doc/current/messenger.html) transport and executed by a worker.

Messages
--------

| Message                                                | Transport         | Handler                                                                                                         |
|--------------------------------------------------------|-------------------|-----------------------------------------------------------------------------------------------------------------|
| `CleverAge\UiProcessBundle\Message\ProcessExecuteMessage` (`code`, `input`, `context`) | `execute_process` | Executes the process with the process manager, like `cleverage:process:execute`.                                |
| `CleverAge\UiProcessBundle\Message\CronProcessMessage` (`processSchedule`)             | `scheduler_cron`  | Dispatches a `ProcessExecuteMessage` with the input and context of the schedule.                                |

You can dispatch a `ProcessExecuteMessage` yourself to queue a process from your code:

```php
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Component\Messenger\MessageBusInterface;

public function __construct(private MessageBusInterface $bus) {}

public function importProducts(string $filePath): void
{
    $this->bus->dispatch(new ProcessExecuteMessage('app.import_products', $filePath, ['delimiter' => ';']));
}
```

Transports
----------

The bundle prepends this configuration to the `framework` extension:

```yaml
framework:
    messenger:
        transports:
            execute_process:
                dsn: 'doctrine://default'
                retry_strategy:
                    max_retries: 0
        routing:
            CleverAge\UiProcessBundle\Message\ProcessExecuteMessage: execute_process
```

Failed processes are not retried. As the configuration is prepended, it can be overridden in your application, e.g.
to use another DSN:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            execute_process:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
```

The `scheduler_cron` transport is created by Symfony Scheduler from the bundle `cron` schedule provider, it needs no
configuration.

Workers
-------

Keep these workers running:

```bash
# Processes launched from the UI, the HTTP API and the scheduler
bin/console messenger:consume execute_process

# UI schedules
bin/console messenger:consume scheduler_cron
```

One `execute_process` worker executes one process at a time: run several workers to execute processes in parallel.
Useful options of `messenger:consume`:

```
  -l, --limit=LIMIT                  Limit the number of received messages
  -f, --failure-limit=FAILURE-LIMIT  The number of failed messages the worker can consume
  -m, --memory-limit=MEMORY-LIMIT    The memory limit the worker can consume
  -t, --time-limit=TIME-LIMIT        The time limit in seconds the worker can handle new messages
      --sleep=SLEEP                  Seconds to sleep before asking for new messages after no messages were found [default: 1]
```

Workers must be restarted after each deployment (`bin/console messenger:stop-workers`) and after any change in the
UI schedules. It is recommended to use [Supervisor](https://symfony.com/doc/current/messenger.html#supervisor-configuration)
or an equivalent to keep them alive:

```ini
[program:scheduler]
command=php /var/www/html/bin/console messenger:consume scheduler_cron
autostart=true
autorestart=true
startretries=1
startsecs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor.scheduler-out.log
user=www-data
killasgroup=true
stopasgroup=true

[program:process]
command=php /var/www/html/bin/console messenger:consume execute_process --time-limit=3600
autostart=true
autorestart=true
startretries=1
startsecs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor.process-out.log
user=www-data
killasgroup=true
stopasgroup=true
```
