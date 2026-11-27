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
| `input`      | Optional process input (string, 255 characters max), e.g. a file path.                                                                                  |
| `context`    | Optional process context, as a list of key/value pairs (see [context](#context)).                                                                      |
| `enabled`    | A disabled schedule is not run (default: enabled). It can be switched directly from the schedules list.                                                 |

Context
-------

The context form of a schedule depends on its process. When the process is
[launched with a form](02-process_ui_options.md) (`ui_launch_mode: form`), the schedule form displays the fields
declared in `ui.form.context` (type, options, default values, field constraints), and the free key/value pairs when
`allow_extra_context` is not `false`. Otherwise, only the free key/value pairs are available.

When the process is changed in the schedule form, only the context row is reloaded (with [htmx](https://htmx.org),
served by AssetMapper: the bundle adds its `assets` directory to `framework.asset_mapper.paths`), with the default
values of the new process (after a confirmation when values have been entered in the context). In production, the scripts
require the assets to be compiled (`bin/console asset-map:compile`, see the [installation](../index.md#assets)). The
`ui.form.input` and `ui.form.constraints` options do not apply to a schedule.

The context is stored as before, a list of `{key, value}` rows, so the existing schedules are kept. The values are
stored as scalars (enums, dates... are converted as when a process is launched), so use form types whose value can be
displayed again from a scalar (e.g. a `ChoiceType` with scalar choices, a date type with `input: string`): a stored
value that a field cannot read is displayed empty.

A stored context key that the launch form of the process does not accept (a key that is no longer declared in
`ui.form.context` while `allow_extra_context` is `false`, or a free pair of a schedule created before the launch form of
its process was declared) is not displayed, and it is removed when the schedule is saved: a warning names these keys
when the schedule is edited. When the launch form of the process is invalid, a warning is displayed and the context is
entered as free key/value pairs.

The schedules list displays the next execution date of the enabled `cron` schedules.

How it works
------------

- The bundle registers a schedule provider named `cron` (`CleverAge\UiProcessBundle\Scheduler\CronScheduler`), so
  Symfony creates the `scheduler_cron` transport. It builds a recurring message for each valid and enabled
  `ProcessSchedule`. Invalid schedules are skipped and logged (`info` level, `scheduler` channel); a schedule that cannot be added (e.g. a duplicate, an expression refused by the Scheduler component) is logged at the `critical` level.
- When a schedule is due, the `scheduler_cron` worker receives a `CronProcessMessage`. It reads the schedule again
  from the database: if it has been disabled or deleted since, the process is not run (logged, `info` level,
  `scheduler` channel). Otherwise, it dispatches a `ProcessExecuteMessage` (with the current schedule input and
  context) to the `execute_process` transport.
- The `execute_process` worker runs the process. Its execution is recorded as any other
  (see [process executions & logs](04-process_executions_and_logs.md)).

So **both workers must be running** (see [messenger](08-messenger.md)):

```bash
bin/console messenger:consume scheduler_cron
bin/console messenger:consume execute_process
```

The schedules page displays a warning when no `scheduler_cron` worker is found in the running processes (this check
uses `ps`, it only detects workers running on the same host as the web server).

The schedules are loaded when the `scheduler_cron` worker starts: **restart the worker after creating a schedule,
enabling it again or changing its type or expression**. Disabling or deleting a schedule, or changing its input or
context, is taken into account at its next run, without restarting the worker. Using a process manager like
Supervisor, you can simply stop it (`bin/console messenger:stop-workers`), it will be restarted automatically. The schedule is not stateful: runs missed
while the worker was stopped are not caught up.

Run **a single `scheduler_cron` worker**: each worker sends the messages of the schedules, so with two workers every
due schedule is executed twice. When [symfony/lock](https://symfony.com/doc/current/lock.html) is installed, the
schedule is locked (`cleverage_ui_process.scheduler.cron` resource of the `lock.factory` service): only the worker
holding the lock sends the messages, the other ones take over when it stops. The default lock store of the
FrameworkBundle (`semaphore` or `flock`) only works between workers of the same host; for workers on several hosts or
containers, use a shared store:

```yaml
# config/packages/lock.yaml
framework:
    lock: '%env(DATABASE_URL)%'
```

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
