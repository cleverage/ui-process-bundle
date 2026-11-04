Messenger & asynchronous execution
==================================

Processes launched from the UI, from the [HTTP API](06-http_api.md) (unless `queue` is `false`) or from the
[scheduler](05-scheduler.md) are never executed by the web request: they are sent to a
[Symfony Messenger](https://symfony.com/doc/current/messenger.html) transport and executed by a worker.

Messages
--------

| Message                                                | Transport         | Handler                                                                                                         |
|--------------------------------------------------------|-------------------|-----------------------------------------------------------------------------------------------------------------|
| `CleverAge\UiProcessBundle\Message\ProcessExecuteMessage` (`code`, `input`, `context`, `deleteInput`) | `execute_process` | Executes the process with the process manager, like `cleverage:process:execute`.                                |
| `CleverAge\UiProcessBundle\Message\CronProcessMessage` (`processSchedule`)             | `scheduler_cron`  | Reads the schedule again, and dispatches a `ProcessExecuteMessage` with its current input and context, unless it has been disabled or deleted. |

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

Uploaded files
--------------

The launch form and the HTTP API set `deleteInput` (`false` by default) when the input is the file they have just
written in the upload directory: the file is deleted once the process has been executed, successfully or not (see the
`cleverage_ui_process.delete_uploaded_files` [parameter](01-bundle_configuration.md#container-parameters)). In a
worker, the file of a failed message is kept while the message will be retried (retry strategy of the transport), or
when it is sent to a failure transport, so that `messenger:failed:retry` executes it with its file. It is deleted once
the message no longer fails, or when it fails again from the failure transport and Symfony drops it (not sent again to
the failure transport it has been received from). A file of a message removed from the failure transport
(`messenger:failed:remove`) is kept. With Symfony 6.4.0 to 6.4.4, which sends a message failing again from the failure
transport back to it, its file is deleted anyway.

The messages queued by the version 3.4 of the bundle have no `deleteInput`: their file is not deleted, you may have to
remove it from the upload directory.

The files left in the upload directory (message removed from the failure transport, dispatch failure, worker killed
during the execution...) can be deleted after a retention by the
[`cleverage:ui-process:purge-uploaded-files`](07-console_commands.md#cleverageui-processpurge-uploaded-files) command:
choose a retention longer than the time a message may wait in a transport, its file being deleted too.

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

### Long processes

The Doctrine transport delivers again a message still being handled after its `redeliver_timeout` (3600 seconds by
default), to consider that its worker has died:

* a process running longer than that is executed a second time, in parallel, by another worker;
* the process of a killed worker (out of memory, `SIGKILL`) is executed again after this delay, although failed
  processes are not retried.

Either set a `redeliver_timeout` longer than your longest process:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            execute_process:
                dsn: 'doctrine://default?redeliver_timeout=86400'
```

Or, with Symfony 7.3 or later and the `pcntl` extension, consume with `--keepalive`: the worker refreshes the delivery
of the message being handled every 5 seconds (or the given interval, lower than the `redeliver_timeout`), and the
message of a killed worker is delivered again after the `redeliver_timeout`:

```bash
bin/console messenger:consume execute_process --keepalive
```

Other transports have their own redelivery settings, see the documentation of the transport.

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

Several workers may execute the same process at the same time: launched twice from the UI or the HTTP API, or due
again from a schedule while its previous execution is not over. With a single worker, the next execution waits for the
end of the previous one, but the process can still be executed from the command line at the same time. A process that
must not run twice at the same time must protect its outputs, see
[the good practices of cleverage/process-bundle](https://github.com/cleverage/process-bundle/blob/main/docs/05-good_practices.md#do-not-execute-a-process-twice-at-the-same-time).

Useful options of `messenger:consume`:

```
  -l, --limit=LIMIT                  Limit the number of received messages
  -f, --failure-limit=FAILURE-LIMIT  The number of failed messages the worker can consume
  -m, --memory-limit=MEMORY-LIMIT    The memory limit the worker can consume
  -t, --time-limit=TIME-LIMIT        The time limit in seconds the worker can handle new messages
      --sleep=SLEEP                  Seconds to sleep before asking for new messages after no messages were found [default: 1]
```

Workers must be restarted after each deployment (`bin/console messenger:stop-workers`) and after creating a UI
schedule, enabling it again or changing its type or expression (see [scheduler](05-scheduler.md#how-it-works)). It is recommended to use [Supervisor](https://symfony.com/doc/current/messenger.html#supervisor-configuration)
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
