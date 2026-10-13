Process executions & logs
=========================

Every process execution is recorded, whatever the way it was launched: UI, [HTTP API](06-http_api.md),
[scheduler](05-scheduler.md), `cleverage:process:execute` console command or `ProcessManager::execute()` in your own
code. Nothing has to be configured in the process itself.

Process executions
------------------

An event subscriber listens to the process bundle events:
- on process start, a `ProcessExecution` is created with the `started` status, the process code, the context and the
  name of the log file,
- on process end, its status becomes `finish` and its end date is set,
- on process failure, its status becomes `failed` and its end date is set, or `stopped` when the process has been
  [stopped from the UI](#stopping-a-process-execution).

When a process runs sub-processes in the same PHP process (e.g. with the `ProcessExecutorTask`), their logs are
attached to the execution of the parent process: only one execution is recorded. Sub-processes started in a new PHP
process (e.g. with the `ProcessLauncherTask`) have their own execution.

Executions are stored in the `process_execution` table (`CleverAge\UiProcessBundle\Entity\ProcessExecution`):

| Field         | Description                                                                                         |
|---------------|-----------------------------------------------------------------------------------------------------|
| `code`        | Process code.                                                                                       |
| `status`      | `started`, `finish`, `failed` or `stopped`.                                                         |
| `startDate`   | Start date.                                                                                         |
| `endDate`     | End date, `null` while the process is running (or if the PHP process was killed).                  |
| `stopRequestedAt` | Date of the stop request made from the UI, see [stopping a process execution](#stopping-a-process-execution). |
| `heartbeatAt` | Last sign of life of the running process, see [not responding executions](#not-responding-executions). |
| `logFilename` | Name of the log file.                                                                               |
| `report`      | Associative array, see [report](#report).                                                          |
| `context`     | Process context (including `execution_user` when launched from the UI).                             |

### Executions list

The "Process > Executions" menu (the dashboard home page) lists the executions, most recent first, with their code,
status, dates, duration, report, context and the `source`/`target` [UI options](02-process_ui_options.md) of the
process. They can be filtered by code, start date and duration (in seconds).

The duration is rendered by the `@CleverAgeUiProcess/admin/field/duration.html.twig` template, with the
`%H hour(s) %I min(s) %S s` format (see [`DateInterval::format()`](https://www.php.net/manual/en/dateinterval.format.php)),
translated in the `messages` domain. To change it, translate this format, or
[override the template](https://symfony.com/doc/current/bundles/override.html#templates) in
`templates/bundles/CleverAgeUiProcessBundle/admin/field/duration.html.twig`, e.g.:

```twig
{{ entity.instance.duration('%a day(s) %H:%I:%S') ?? '-' }}
```

These actions are available on each execution:
- "Show logs stored in database": opens the [logs list](#logs-list) filtered on this execution (displayed only if
  some logs were stored),
- "Download log file": downloads the [log file](#log-file) (displayed only if the file exists),
- "Stop the process": on a running execution, see [stopping a process execution](#stopping-a-process-execution),
- "Mark as failed": on an execution which is not responding, see [not responding executions](#not-responding-executions).

The process list also displays the date and status of the last execution of each process, and a link to its
executions.

### Stopping a process execution

A running execution (`started` status) can be stopped from the executions list with the "Stop the process" action
(after a confirmation). It only records a stop request (`stopRequestedAt`): the process may run anywhere (Messenger
worker, web request, console, another server).

The running process reads it with the `ProcessExecutionStopChecker`, a
[stop checker](https://github.com/cleverage/process-bundle/blob/main/docs/04-advanced_workflow.md#stopping-a-running-process)
of the process bundle called before each task execution. To keep it cheap, the stop request is read at most every
`process_execution.heartbeat_interval` seconds (default `5`, see
[bundle configuration](01-bundle_configuration.md#process_execution)), with a DBAL query. When it is found, the
process stops before its next task execution, as a failure (the remaining items are not flushed, the tasks are not
finalized), and the execution gets the `stopped` status. Until then, the execution displays "Stop requested".

Keep in mind that:
- The process stops between two task executions: a task blocked in a single long call (SQL query, HTTP request,
  sub-process started by the `ProcessLauncherTask`) stops only when the call returns. The sub-processes started in a
  new PHP process are not stopped.
- The process bundle logs the failure (`Critical process failure`), so a `Critical` counter is added to the
  [report](#report).
- The `stopped` executions are not [notified](09-notifications.md) by default: add `stopped` to the notified
  `statuses`.

### Not responding executions

When the PHP process running an execution is killed (worker restarted or crashed, out of memory, container stopped),
its execution keeps the `started` status: the end of the process is never recorded.

While the process runs, the `ProcessExecutionStopChecker` updates the heartbeat of its execution (`heartbeatAt`) at
most every `heartbeat_interval` seconds, before a task execution. A `started` execution without heartbeat (or start,
without heartbeat yet) for more than `process_execution.stale_after` seconds (default `900`) is displayed as "Not
responding", with the date of its last activity. It cannot be stopped anymore, but it can be marked as failed with the
"Mark as failed" action (after a confirmation): its status becomes `failed`, and its end date is set to its last
activity.

A process blocked in a single long task call does not update its heartbeat either: set `stale_after` above the
longest task call of your processes, and only mark as failed an execution whose process is no longer running (if it
is still running, its real status is recorded at its end).

### Report

When a log record has a level greater than or equal to `report_increment_level` (default `Warning`, see
[bundle configuration](01-bundle_configuration.md#logs)), a counter named after the level is incremented in the
execution report, e.g. `{"Warning": 12, "Error": 1}`. This gives an overview of the problems of an execution
without opening its logs.

Logs
----

The bundle registers two Monolog handlers, filtered on the `cleverage_process` and `cleverage_process_task`
channels (the channels of the process bundle, used by the `LoggerTask` and every task logger):

| Monolog handler | Service                                            | Destination                         | Level option       |
|-----------------|----------------------------------------------------|-------------------------------------|--------------------|
| `pb_ui_file`    | `cleverage_ui_process.monolog_handler.process`     | [Log file](#log-file)               | `file_level`       |
| `pb_ui_orm`     | `cleverage_ui_process.monolog_handler.doctrine_process` | [Database](#database-logs)     | `database_level`   |

Both levels default to `Debug` on the `dev` environment and to `Info` otherwise. Your own Monolog handlers are not
modified.

### Log file

Each execution writes its own log file:

```
%kernel.logs_dir%/<process_code>/<uuid>.log
```

It can be downloaded from the executions list.

### Database logs

When `store_in_database` is `true` (default), log records are also stored in the `log_record` table
(`CleverAge\UiProcessBundle\Entity\LogRecord`), linked to their execution (and deleted with it):

| Field       | Description                                   |
|-------------|-----------------------------------------------|
| `channel`   | Monolog channel (truncated to 64 characters). |
| `level`     | Monolog level value.                          |
| `message`   | Message (truncated to 512 characters).        |
| `context`   | Log record context.                           |
| `createdAt` | Date of the log record.                       |

Records are buffered and inserted every 500 records, and at the end (or failure) of the process.

On processes logging a lot, storing logs in database may be slow and memory consuming: raise `database_level`, or
disable `store_in_database` and rely on the log file (see [troubleshooting](../troubleshooting.md)).

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    logs:
        store_in_database: true
        database_level: Warning # Only warnings and errors in database
        file_level: Debug # Everything in the log file
        report_increment_level: Error
```

### Logs list

The "Process > Logs" menu lists the database logs (250 per page), with their level, message, date and a "Has context
info ?" column. The detail page displays the log context. They can be filtered by process (or execution), level,
message, context and date.
