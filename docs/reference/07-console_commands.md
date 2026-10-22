Console commands
================

cleverage:ui-process:user-create
--------------------------------

Creates a UI user with the `ROLE_USER` and `ROLE_ADMIN` roles. Use it to create the first administrator, then manage
users from the UI (see [users & security](03-users_and_security.md)).

```bash
bin/console cleverage:ui-process:user-create [<email> [<password>]]
```

| Argument   | Required | Description                                                                                                  |
|------------|:--------:|--------------------------------------------------------------------------------------------------------------|
| `email`    |          | Email (login) of the user. Asked interactively if missing, and then validated as an email address.          |
| `password` |          | Password of the user. Asked interactively (hidden) if missing, and then required to be at least 8 characters long. |

```bash
# Interactive
bin/console cleverage:ui-process:user-create

# Non-interactive, e.g. in a provisioning script
bin/console cleverage:ui-process:user-create admin@example.com "$ADMIN_PASSWORD"
```

Values passed as arguments are not validated. The email must be unique. A password given as argument is visible in the
process list (`ps`) while the command runs and, when typed, in the shell history: prefer the interactive prompt, or a
variable as above.

cleverage:ui-process:purge-logs
-------------------------------

Deletes the log records stored in database and the log files older than the configured retention
(`logs.database_max_days` and `logs.file_max_days`, overridable by process). The process executions are kept. See
[log retention](04-process_executions_and_logs.md#log-retention).

```bash
bin/console cleverage:ui-process:purge-logs [--dry-run]
```

| Option      | Description                                                                   |
|-------------|-------------------------------------------------------------------------------|
| `--dry-run` | Counts the log records and the log files to delete, without deleting them.   |

It displays, for each process with a retention, the retention and the number of log records and log files deleted.
Without any retention configured, it does nothing.

Related commands
----------------

The bundle relies on the following commands of other bundles:

| Command                                                  | Description                                                                                                  |
|----------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| `bin/console doctrine:migrations:migrate`                | Creates/updates the bundle tables (`process_user`, `process_execution`, `log_record`, `process_schedule`).   |
| `bin/console messenger:consume execute_process`          | Executes the processes launched from the UI, the HTTP API or the scheduler. See [messenger](08-messenger.md). |
| `bin/console messenger:consume scheduler_cron`           | Triggers the schedules defined in the UI. See [scheduler](05-scheduler.md).                                  |
| `bin/console cleverage:process:execute <code>`           | Executes a process synchronously (process bundle). The execution is recorded in the UI too.                 |
| `bin/console assets:install`                             | Installs the default logo in `public/bundles/cleverageuiprocess/`.                                           |
