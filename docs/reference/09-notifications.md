Notifications
=============

The end of a process execution can be notified (Slack, Teams, email, SMS...) with
[symfony/notifier](https://symfony.com/doc/current/notifier.html), whatever the way the process was launched (UI,
console, HTTP API, scheduler). Only the top-level process is notified, not its sub-processes.

Installation
------------

`symfony/notifier` is an optional dependency of the bundle:

```bash
composer require symfony/notifier
```

Then configure the notifier itself (transports, channel policy, admin recipients), see the
[Symfony documentation](https://symfony.com/doc/current/notifier.html), e.g.:

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            slack: '%env(SLACK_DSN)%'
        channel_policy:
            high: ['chat/slack', 'email']
            medium: ['chat/slack']
            low: ['chat/slack']
        admin_recipients:
            - { email: 'ops@example.com' }
```

Enabling `clever_age_ui_process.notification.enabled` without `symfony/notifier` installed raises an error when
the container is built. If `symfony/notifier` is installed but the notifier is not enabled (`framework.notifier`),
nothing is sent and a warning is logged.

Configuration
-------------

The notifications are disabled by default. They are configured for every process in the
[bundle configuration](01-bundle_configuration.md#notification), and each key can be overridden by process, under the
`notification` key of its options (next to the [`ui` options](02-process_ui_options.md)):

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    notification:
        enabled: true
        statuses: [failed, finish_with_report] # default

# config/packages/process/app.daily_import.yaml
clever_age_process:
    configurations:
        app.daily_import:
            options:
                notification:
                    statuses: [failed, finish_with_report, finish] # also notify the successful imports
                    channels: ['email']
                    recipients:
                        - { email: 'sales@example.com' }
            tasks:
                # ...
        app.cache_warmup:
            options:
                notification:
                    enabled: false # never notified
            tasks:
                # ...
```

| Key          | Type       | Description                                                                                                                  |
|--------------|------------|------------------------------------------------------------------------------------------------------------------------------|
| `enabled`    | `bool`     | Notify the end of the executions of this process.                                                                            |
| `statuses`   | `string[]` | Ends of process executions to notify, see [statuses](#statuses).                                                              |
| `channels`   | `string[]` | Notifier channels, e.g. `chat/slack`, `email`. Empty: the `channel_policy` of the notifier, by importance.                   |
| `recipients` | `array[]`  | Recipients, with an `email` and/or a `phone` (required by the `email` and `sms` channels). Empty: the `admin_recipients` of the notifier. |

A key missing (or `null`) in the process options is inherited from the bundle configuration. Invalid process options
raise an options resolver error.

Statuses
--------

| Status               | End of the process execution                                                                                                       | Importance |
|----------------------|------------------------------------------------------------------------------------------------------------------------------------|------------|
| `failed`             | Failed (status `failed`).                                                                                                          | `high`     |
| `finish_with_report` | Finished (status `finish`) with log levels counted in its [report](04-process_executions_and_logs.md#report), e.g. `{"Warning": 3}`. The counted levels depend on `logs.report_increment_level` (default `Warning`). | `medium`   |
| `finish`             | Finished without log levels counted in its report.                                                                                | `low`      |

The importance selects the channels of the notifier `channel_policy` when no `channels` are configured.

Notification
------------

The notification (`CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotification`) contains:
- the subject, e.g. `Process "app.daily_import" failed`,
- the status, the start date and the duration of the execution, the log levels counted in its report, the error
  message (failed execution), the log file and the id of the process execution,
- the exception of a failed execution (its trace is added to the emails).

What is displayed depends on the channel and on the transport: the emails contain the subject, the content and the
exception, the chat messages contain the subject, plus the content and the exception for some transports (e.g.
Slack), the SMS contain the subject.

An error while sending the notification (unknown channel, transport failure...) is logged (`cleverage_ui_process`
Monolog channel) and does not change the result of the process.

Custom notifications
--------------------

The bundle dispatches a `CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent` when a top-level process
execution has ended and has been saved, with the `ProcessExecution` entity (status, dates, report, context...) and
the error of a failed execution. The notification is sent by a listener of this event. To send your own
notifications (or anything else), keep `notification.enabled` to `false` and listen to this event:

```php
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ProcessExecutionEndedListener
{
    public function __invoke(ProcessExecutionEndedEvent $event): void
    {
        if (ProcessExecutionStatus::Failed === $event->processExecution->status) {
            // ...
        }
    }
}
```

This event does not require `symfony/notifier`. The events of the process bundle (`cleverage_process.end`,
`cleverage_process.fail`, see the
[process bundle documentation](https://github.com/cleverage/process-bundle/blob/main/docs/04-advanced_workflow.md#events))
are dispatched for every process, sub-processes included, before the process execution is saved.
