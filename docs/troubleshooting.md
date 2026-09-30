Troubleshooting
===============

## A process launched from the UI never starts

Processes launched from the UI, the HTTP API (queued) or the scheduler are executed by a Messenger worker. Make sure
`bin/console messenger:consume execute_process` is running (see [messenger](reference/08-messenger.md)).

## A scheduled process is not executed

- Make sure both `bin/console messenger:consume scheduler_cron` and `bin/console messenger:consume execute_process`
  are running.
- Schedules are loaded when the `scheduler_cron` worker starts: restart it after any change in the scheduler.
- Invalid schedules (unknown or private process, invalid expression) are skipped and logged on the `scheduler`
  channel.

See [scheduler](reference/05-scheduler.md).

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
