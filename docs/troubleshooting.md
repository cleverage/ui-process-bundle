Troubleshooting
===============

## A process launched from the UI never starts

Processes launched from the UI, the HTTP API (queued) or the scheduler are executed by a Messenger worker. Make sure
`bin/console messenger:consume execute_process` is running (see [messenger](reference/08-messenger.md)).

## A scheduled process is not executed

- Make sure both `bin/console messenger:consume scheduler_cron` and `bin/console messenger:consume execute_process`
  are running.
- Schedules are loaded when the `scheduler_cron` worker starts: restart it after creating a schedule, enabling it
  again or changing its type or expression.
- Disabled schedules are not run: check the "Enabled" switch in the schedules list.
- Invalid schedules (unknown or private process, invalid expression) are skipped and logged on the `scheduler`
  channel.

See [scheduler](reference/05-scheduler.md).

## A stopped process is still running

The stop request is read before each task execution, at most every `process_execution.heartbeat_interval` seconds
(default `5`): a task blocked in a long call (SQL query, HTTP request) stops only when the call returns. If the
execution is displayed as "Not responding", its process has probably been killed: mark it as failed.

See [stopping a process execution](reference/04-process_executions_and_logs.md#stopping-a-process-execution).

## An execution stays "started" forever

Its PHP process has probably been killed (worker restarted or crashed, out of memory). After
`process_execution.stale_after` seconds without heartbeat (default `900`), it is displayed as "Not responding" and can
be marked as failed.

See [not responding executions](reference/04-process_executions_and_logs.md#not-responding-executions).

## PHP Fatal error: Allowed memory size of xxx bytes exhausted

When the `store_in_database` option is enabled with a low `database_level`, the process may generate many
`LogRecord`. On debug environment, profiling too many queries causes memory exhaustion. So, you can:
- Set `doctrine.dbal.profiling_collect_backtrace: false`
- Increase `memory_limit` in php.ini
- Set `clever_age_ui_process.logs.store_in_database: false` or raise `clever_age_ui_process.logs.database_level`
- Use `--no-debug` flag for `cleverage:process:execute`

See [process executions & logs](reference/04-process_executions_and_logs.md).

## {"message":"Missing auth token."} response when launching a process via HTTP request

If you use the Apache web server with PHP-FPM/FastCGI, the `Authorization` header may not be passed to PHP. Add (or
uncomment) the following directive in your VirtualHost:

```apache
SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1
```

See [HTTP API](reference/06-http_api.md).
