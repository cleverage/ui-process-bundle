Schedule a recurring process
============================

This recipe runs a Doctrine purge process every night, and lets operators change its schedule or its parameters from the UI
without any deployment.

```yaml
# config/packages/process/app.purge_authors.yaml
clever_age_process:
    configurations:
        app.purge_authors:
            description: 'Remove the authors with a given lastname, and their books'
            help: 'Ex: bin/console cleverage:process:execute app.purge_authors -c lastname:"King"'
            options:
                ui:
                    source: Database
                    target: Database
                    ui_launch_mode: form # Manual launches from the process list can set the lastname too
                    default:
                        context:
                            - key: lastname
                              value: King
            tasks:
                read:
                    service: '@CleverAge\DoctrineProcessBundle\Task\EntityManager\DoctrineReaderTask'
                    options:
                        class_name: 'App\Entity\Author'
                        criteria:
                            lastname: '{{ lastname }}' # Contextualized option, set by the schedule
                    outputs: [log, remove]

                log:
                    service: '@CleverAge\ProcessBundle\Task\Reporting\LoggerTask'
                    options:
                        level: warning # Counted in the execution report
                        message: 'Removing author'

                remove:
                    service: '@CleverAge\DoctrineProcessBundle\Task\EntityManager\DoctrineRemoverTask'
```

Then, in the UI, open "Process > Scheduler" and create a schedule:

| Tab     | Field      | Value                                    |
|---------|------------|------------------------------------------|
| General | Process    | `app.purge_authors`                        |
| General | Type       | `cron`                                   |
| General | Expression | `0 3 * * *` (every day at 3 AM)          |
| Input   | Input      | *(empty)*                                |
| Context | Context    | key `lastname`, value `King`             |

Finally, make sure both workers are running, and restart the `scheduler_cron` one so that it loads the new schedule:

```bash
bin/console messenger:consume scheduler_cron
bin/console messenger:consume execute_process
```

How it works:
- The process must be public (the default) to be selectable in the scheduler
  (see [process visibility](../reference/02-process_ui_options.md#process-visibility)). The `ui` options only
  concern manual launches from the process list: they are not used by the scheduler.
- The schedule is stored as a `ProcessSchedule`. Its expression is validated according to its type: a cron
  expression for `cron`, a relative time such as `10 minutes` for `every` (see
  [process schedules](../reference/05-scheduler.md#process-schedules)). The schedules list displays the next
  execution date.
- The `scheduler_cron` worker loads the schedules when it starts. When one is due, it dispatches the process, with the
  schedule input and context, to the `execute_process` transport, where the other worker executes it
  (see [how it works](../reference/05-scheduler.md#how-it-works) and [messenger](../reference/08-messenger.md)).
- The context pairs of the schedule are injected into the contextualized options, like `-c lastname:King` on the
  console: the same process can be scheduled several times with different contexts.
- Each run appears in "Process > Executions" with its status, duration and a report counting the `Warning` logs, i.e.
  the number of removed authors (see [report](../reference/04-process_executions_and_logs.md#report)).

In production, keep the workers alive with Supervisor (see [workers](../reference/08-messenger.md#workers)), and
restart the `scheduler_cron` worker after each change in the scheduler, e.g. with
`bin/console messenger:stop-workers`.
