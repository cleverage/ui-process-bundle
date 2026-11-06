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
- on process end, its status becomes `finish` and its end date is set, or `stopped` when the process has been stopped
  on purpose by a [StopTask](https://github.com/cleverage/process-bundle/blob/main/docs/reference/tasks/stop_task.md)
  (e.g. not going on when the source file does not exist),
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
| `context`     | Process context (including `execution_user` when launched from the UI). Stored as JSON: a context which cannot be encoded (e.g. a value of a multipart request that is not valid UTF-8) is stored as the context of the log records, with its invalid UTF-8 sequences replaced by `U+FFFD` (`�`) and its values which cannot be encoded replaced by `null`; the process itself receives the context unchanged. |

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
responding", with the date of its last activity. It can be marked as failed with the "Mark as failed" action (after a
confirmation): its status becomes `failed`, and its end date is set to its last activity. This only updates the
execution: it does not stop its process.

A process blocked in a single long task call does not update its heartbeat either: set `stale_after` above the
longest task call of your processes. A not responding execution can still be stopped: if its process is in fact
blocked in a long task call, it stops when the call returns; if it has been killed, the stop request is never read.
Only mark as failed an execution whose process is no longer running (if it is still running, it goes on until its
end, and its real status is recorded then).

The dates of the executions and of their logs are stored without timezone, in the default timezone of the PHP process
writing them (`date.timezone`), and compared with the current date of the PHP process reading them. **Use the same
`date.timezone` for the PHP CLI (workers, commands) and the web server (PHP-FPM)**, e.g. in separate containers:
otherwise, a running execution is displayed as "Not responding" as soon as it starts (worker behind the web server, e.g.
`UTC` and `Europe/Paris`), or a killed one only after the time difference.

### Report

When a log record has a level greater than or equal to `report_increment_level` (default `Warning`, see
[bundle configuration](01-bundle_configuration.md#logs)), a counter named after the level is incremented in the
execution report, e.g. `{"Warning": 12, "Error": 1}`. This gives an overview of the problems of an execution
without opening its logs.

### In your code

The services of the bundle can be autowired by their class name:

| Class                                                             | Service                                              | Usage                                                                                                                                                         |
|-------------------------------------------------------------------|------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `CleverAge\UiProcessBundle\Manager\ProcessExecutionManager`       | `cleverage_ui_process.manager.process_execution`     | Execution of the running process: `getCurrentProcessExecution()`, `increment('<key>')` / `setReport('<key>', '<value>')` to add your own entries to its report. |
| `CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository` | `cleverage_ui_process.repository.process_execution`  | `getLastProcessExecution('<process code>')`: last execution of a process.                                                                                     |
| `CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository`  | `cleverage_ui_process.repository.process_schedule`   | `findEnabled()`: enabled [schedules](05-scheduler.md).                                                                                                        |
| `CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager`  | `cleverage_ui_process.manager.process_configuration` | `getPublicProcesses()`, `getPrivateProcesses()` and the resolved [UI options](02-process_ui_options.md) of a process (`getUiOptions('<process code>')`).       |

```php
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;

final class MyService
{
    public function __construct(private readonly ProcessExecutionManager $processExecutionManager)
    {
    }

    public function doSomething(): void
    {
        // Counted in the report of the running execution (nothing happens outside of a process)
        $this->processExecutionManager->increment('imported');
    }
}
```

Logs
----

The bundle registers two Monolog handlers, filtered on the `cleverage_process` and `cleverage_process_task`
channels (the channels of the process bundle, used by the `LoggerTask` and every task logger):

| Monolog handler | Service                                            | Destination                         | Level option       |
|-----------------|----------------------------------------------------|-------------------------------------|--------------------|
| `pb_ui_file`    | `cleverage_ui_process.monolog_handler.process`     | [Log file](#log-file)               | `file_level`       |
| `pb_ui_orm`     | `cleverage_ui_process.monolog_handler.doctrine_process` | [Database](#database-logs)     | `database_level`   |

Both levels default to `Debug` on the `dev` environment and to `Info` otherwise. Your own Monolog handlers are not
modified. The channels are filtered by the `pb_ui_file_filter` and `pb_ui_orm_filter` handlers, whose minimum level is
the level of the handler they wrap: the Monolog processors do not run for a record none of them writes (e.g. the debug
record of each task execution, with the `Info` level), unless another handler of the channel accepts it.

### Log file

Each execution writes its own log file:

```
%kernel.logs_dir%/<process_code>/<uuid>.log
```

It can be downloaded from the executions list (streamed: its size is not limited by the PHP memory limit).

The control characters of the messages (e.g. coming from the processed data) are escaped, so that a terminal displaying
the file (`cat`, `tail -f`) does not interpret them: the C0 characters except the tabulation and the line feed (an ANSI
escape sequence `ESC[31m` is written `\x1B[31m`), DEL (`\x7F`) and the C1 characters (`\u{9B}`). The context is
encoded in JSON, where they are escaped too.

### Database logs

When `store_in_database` is `true` (default), log records are also stored in the `log_record` table
(`CleverAge\UiProcessBundle\Entity\LogRecord`), linked to their execution (and deleted with it):

| Field       | Description                                   |
|-------------|-----------------------------------------------|
| `channel`   | Monolog channel (truncated to 64 characters). |
| `level`     | Monolog level value.                          |
| `message`   | Message (truncated to 512 characters).        |
| `context`   | Log record context (limited, see below).      |
| `createdAt` | Date of the log record.                       |

The lengths are counted in characters (Unicode code points, as MySQL and PostgreSQL do): an emoji or a letter with
combining marks may count several characters. The invalid UTF-8 sequences of the message and the channel are replaced
by `?` and their NUL characters removed; a context which cannot be encoded in JSON is stored with its invalid UTF-8 sequences replaced by `U+FFFD` (`�`)
and its values which cannot be encoded (e.g. a resource) replaced by `null`. As in the log file (Monolog
`NormalizerFormatter`), the arrays of the context are limited to 1000 items (the next ones are replaced by a `...` key,
`Over 1000 items (<count> total), aborting normalization`) and to 9 levels deep, the context being the first one (the
deeper values are replaced by `Over 9 levels deep, aborting normalization`): an unbounded context (e.g. the output of an
aggregator) was stored whole, and over the maximum packet size of the database, it made the insertion of the log records
written with it fail. The objects of the context are stored with their public properties, without limit.

Records are buffered and inserted every 500 records, and at the end (or failure) of the process. A record counted in
the report of the execution (from `report_increment_level`, warnings by default) is inserted right away with the
buffered ones, unless changes of the process tasks are pending in the entity manager (they would be written with it, see
below): it is then inserted with the next ones. After a fatal error (e.g. maximum execution time exceeded), neither the
end of the process nor the destruction of the handler happen: a shutdown function inserts the buffered records and saves
the report of the execution, unless changes of the process tasks are pending (the status of the execution stays
`started`, see [not responding executions](#not-responding-executions)). After the memory limit is exceeded, the limit is raised by 32 MiB
to do it, but the shutdown function of the Symfony `ErrorHandler`, called first, usually fails too (no memory left), and
the next ones are not called: only the records counted in the report, inserted right away, are kept in database (the log
file has all of them). When the insertion of
the log records fails (e.g. a database error), they are dropped and the failure is logged as an error on the
`cleverage_process` channel (in the [log file](#log-file), not in database): the process goes on, and the status of its
execution is saved. The entity manager is shared with the process tasks (unless [another one is
configured](#entity-manager-of-the-process-executions)): when changes of their entities are pending
(e.g. persisted by a task, not flushed yet), they are written with the log records, and a failure is not caught, as any
error of the tasks. To know it, when entities of the tasks are managed by the entity manager, their changes are computed
before each insertion of the log records (and again by the flush): a cost proportional to the number of managed
entities, every 500 log records.

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

### Entity manager of the process executions

By default, the process executions, their logs and their heartbeat are written with the default entity manager
(`doctrine.orm.entity_manager`), so on the connection of the process tasks:

- a task reading with an unbuffered query (e.g. `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` set to `false` for a large
  export) fails as soon as the log records are inserted during its read ("Cannot execute queries while other
  unbuffered queries are active"), while the same process works without the UI;
- a transaction rolled back by a task also rolls back the log records and the state of the execution written meanwhile;
- while a task keeps a transaction open, the heartbeat locks the row of the execution until the transaction ends: the
  "Stop" and "Mark as failed" actions of the UI wait for the lock and fail after the lock wait timeout of the database
  (`innodb_lock_wait_timeout`, 50 seconds by default on MySQL);
- the pending changes of the entities of the tasks are written with the log records (see above).

To isolate them, give the name of an entity manager on a connection of its own, mapping the entities of the bundle, in
the `process_execution.entity_manager` option:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                url: '%env(resolve:DATABASE_URL)%'
            # Same database, another connection
            process_ui:
                url: '%env(resolve:DATABASE_URL)%'
    orm:
        default_entity_manager: default
        entity_managers:
            default:
                connection: default
                auto_mapping: true # also maps the entities of the bundle, read by the UI
                # mappings of your application...
            process_ui:
                connection: process_ui
                mappings:
                    CleverAgeUiProcessBundle:
                        type: attribute
                        is_bundle: true

# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    process_execution:
        entity_manager: process_ui
```

The entity manager is the `doctrine.orm.<name>_entity_manager` service. The `ProcessExecutionRepository` service of the
bundle uses it too (reset by its name when it has been closed); the pages of the UI read the entities with the entity
manager managing their class (the default one in this example). Each running process then opens one more connection to
the database. The migrations of the bundle are still executed on the connection of Doctrine Migrations (the same
database). With SQLite, two connections writing to the same file may wait for each other's lock: keep the default.

### Log retention

Neither the log records stored in database nor the log files are deleted by default. The
`cleverage:ui-process:purge-logs` command deletes those older than a retention, in days:

| Option              | Deletes                                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------------------|
| `database_max_days` | The log records (`log_record` table) created more than `database_max_days` days ago.                     |
| `file_max_days`     | The log files (`<code>/<uuid>.log` in `%kernel.logs_dir%`) last modified more than `file_max_days` days ago. |

Both default to `null` (kept), in the [bundle configuration](01-bundle_configuration.md#logs), and can be overridden
by process under the `logs` key of its options (next to the [`ui` options](02-process_ui_options.md)). A key missing
(or `null`) in the process options is inherited from the bundle configuration; the executions of a process no longer
defined use the bundle configuration, as those of a process whose configuration is not valid (e.g. an unknown task in
its `outputs`, an invalid `logs` option), with a warning naming it: the other processes are still purged. An invalid
`ui` or `notification` option of a process does not affect the purge of its logs.

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    logs:
        database_max_days: 30
        file_max_days: 180

# A process whose logs are kept longer
clever_age_process:
    configurations:
        app.daily_import:
            options:
                logs:
                    database_max_days: 90
                    file_max_days: 365
            tasks:
                # ...
```

Schedule the command, e.g. every night with a cron:

```
0 3 * * * cd /path/to/project && bin/console cleverage:ui-process:purge-logs --no-debug
```

Run it with `--dry-run` first to count what would be deleted.

Keep in mind that:
- The process executions are never modified nor deleted: the executions list, their statuses and reports stay
  complete. Once its logs are purged, the "Show logs stored in database" and "Download log file" actions of an
  execution are no longer displayed.
- The log file of an execution contains its log records (the `message` is not truncated), as long as `file_level` is
  not higher than `database_level`: keep the files longer than the database logs to keep the logs readable.
- Only the log files of the bundle are deleted: files named `<uuid>.log`, in the directory of a process code.
- The log records are deleted by batches of 1000.

#### Database space

Deleting rows does not shrink the `log_record` table on disk: the database reuses the freed space for the new log
records, so that with regular purges the table stops growing. To give the space back after the first purge, rebuild
the table during a maintenance window (no process running: the table is locked while it is rebuilt):

| Database            | Command                       |
|---------------------|-------------------------------|
| PostgreSQL          | `VACUUM FULL log_record;`     |
| MySQL / MariaDB     | `OPTIMIZE TABLE log_record;`  |
| SQLite              | `VACUUM;` (whole database)    |

The command does not run them: they would block the running processes, which write their logs in this table.

### Logs list

The "Process > Logs" menu lists the database logs (250 per page), with their level, message, date and a "Has context
info ?" column. The detail page displays the log context. They can be filtered by process (or execution), level,
message, context and date. The message and context filters are case-insensitive, on every database. The context
filter searches the JSON representation of the context, as stored by the database (e.g. with escaped non-ASCII
characters on PostgreSQL).

The list counts the log records of the current filters, and the message and context filters read every log record
(`LIKE '%...%'`, which no index can serve): on large tables they take seconds per page (with 3 million log records,
about 2.3 s for the count of a page and 3.6 s with a message filter). Purge the logs regularly in production (see
[log retention](#log-retention)), and filter by process or execution first.
