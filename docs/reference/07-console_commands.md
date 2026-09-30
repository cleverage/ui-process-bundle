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
bin/console cleverage:ui-process:user-create admin@example.com 'my-secret-password'
```

Values passed as arguments are not validated. The email must be unique.

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
