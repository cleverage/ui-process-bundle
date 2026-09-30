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
- on process failure, its status becomes `failed` and its end date is set.

When a process runs sub-processes in the same PHP process (e.g. with the `ProcessExecutorTask`), their logs are
attached to the execution of the parent process: only one execution is recorded. Sub-processes started in a new PHP
process (e.g. with the `ProcessLauncherTask`) have their own execution.

Executions are stored in the `process_execution` table (`CleverAge\UiProcessBundle\Entity\ProcessExecution`):

| Field         | Description                                                                                         |
|---------------|-----------------------------------------------------------------------------------------------------|
| `code`        | Process code.                                                                                       |
| `status`      | `started`, `finish` or `failed`.                                                                    |
| `startDate`   | Start date.                                                                                         |
| `endDate`     | End date, `null` while the process is running (or if the PHP process was killed).                  |
| `logFilename` | Name of the log file.                                                                               |
| `report`      | Associative array, see [report](#report).                                                          |
| `context`     | Process context (including `execution_user` when launched from the UI).                             |

### Executions list

The "Process > Executions" menu (the dashboard home page) lists the executions, most recent first, with their code,
status, dates, duration, report, context and the `source`/`target` [UI options](02-process_ui_options.md) of the
process. They can be filtered by code, start date and duration (in seconds).

Two actions are available on each execution:
- "Show logs stored in database": opens the [logs list](#logs-list) filtered on this execution (displayed only if
  some logs were stored),
- "Download log file": downloads the [log file](#log-file) (displayed only if the file exists).

The process list also displays the date and status of the last execution of each process, and a link to its
executions.

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
