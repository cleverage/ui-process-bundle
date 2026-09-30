Scheduler
=========

Processes can be scheduled from the UI ("Process > Scheduler" menu), with a cron expression or a periodical
expression. The bundle relies on the [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html) component.

Process schedules
-----------------

Schedules are stored in the `process_schedule` table (`CleverAge\UiProcessBundle\Entity\ProcessSchedule`):

| Field        | Description                                                                                                                                             |
|--------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `process`    | Code of the process to run. Only public processes can be selected, and the code is validated.                                                         |
| `type`       | `cron` or `every`.                                                                                                                                      |
| `expression` | `cron`: a cron expression, e.g. `*/5 * * * *` or `@daily` (see [cron expression triggers](https://symfony.com/doc/current/scheduler.html#cron-expression-triggers)). `every`: a relative time, e.g. `5 seconds`, `1 hour`, `1 day` (see [periodical triggers](https://symfony.com/doc/current/scheduler.html#periodical-triggers)); it must be parsable by `strtotime()`. |
| `input`      | Optional process input (string), e.g. a file path.                                                                                                    |
| `context`    | Optional list of key/value pairs, passed as process context.                                                                                           |

The schedules list displays the next execution date of `cron` schedules.

How it works
------------

- The bundle registers a schedule provider named `cron` (`CleverAge\UiProcessBundle\Scheduler\CronScheduler`), so
  Symfony creates the `scheduler_cron` transport. It builds a recurring message for each valid `ProcessSchedule`.
  Invalid schedules are skipped and logged (`info` level, `scheduler` channel).
- When a schedule is due, the `scheduler_cron` worker receives a `CronProcessMessage` and dispatches a
  `ProcessExecuteMessage` (with the schedule input and context) to the `execute_process` transport.
- The `execute_process` worker runs the process. Its execution is recorded as any other
  (see [process executions & logs](04-process_executions_and_logs.md)).

So **both workers must be running** (see [messenger](08-messenger.md)):

```bash
bin/console messenger:consume scheduler_cron
bin/console messenger:consume execute_process
```

The schedules page displays a warning when no `scheduler_cron` worker is found in the running processes (this check
uses `ps`, it only detects workers running on the same host as the web server).

The schedules are loaded when the `scheduler_cron` worker starts: **restart the worker after creating, updating or
deleting a schedule**. Using a process manager like Supervisor, you can simply stop it
(`bin/console messenger:stop-workers`), it will be restarted automatically. The schedule is not stateful: runs missed
while the worker was stopped are not caught up.

See the [scheduled process](../cookbooks/scheduled_process.md) cookbook for a complete example.

Application schedules
---------------------

The UI scheduler is independent of the schedules defined in your application code. To schedule a process from code,
you can dispatch a `CleverAge\UiProcessBundle\Message\ProcessExecuteMessage` from your own
[schedule provider](https://symfony.com/doc/current/scheduler.html#attaching-recurring-messages-to-a-schedule):

```php
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('app')]
class AppSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::cron('0 2 * * *', new ProcessExecuteMessage('app.import_products', null, ['source' => 'erp'])),
        );
    }
}
```

This requires a `bin/console messenger:consume scheduler_app` worker, which handles the `ProcessExecuteMessage`
itself (messages received from a scheduler transport are not sent to `execute_process`). The executions are recorded
as usual, but these schedules are not displayed in the UI.
