Bundle configuration
====================

YAML Configuration
------------------

Every key is optional. Default values are shown below (also displayed, with the description of each option, by
`bin/console config:dump-reference clever_age_ui_process`; `bin/console debug:config clever_age_ui_process` shows the
current configuration).

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    security:
        roles: ['ROLE_ADMIN']
        configure_firewall: true
    logs:
        store_in_database: true
        database_level: Info # Debug when kernel.environment is "dev"
        file_level: Info # Debug when kernel.environment is "dev"
        report_increment_level: Warning
        database_max_days: ~
        file_max_days: ~
    design:
        logo_path: 'bundles/cleverageuiprocess/logo.jpg'
    notification:
        enabled: false
        statuses: [failed, finish_with_report]
        channels: []
        recipients: []
    process_execution:
        heartbeat_interval: 5
        stale_after: 900
```

Options
-------

### security

| Key     | Type       | Default          | Description                                                                                                                                       |
|---------|------------|------------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `roles` | `string[]` | `['ROLE_ADMIN']` | Roles that can be assigned to a user in the UI user edit form. `ROLE_USER` is always granted to every user (see [users & security](03-users_and_security.md)). |
| `configure_firewall` | `bool` | `true` | Add the `process_user_provider` user provider and configure the `main` firewall of the application (overriding its provider, login form and logout). `false`: no `security` configuration is added, configure it in your application (see [users & security](03-users_and_security.md#configuring-the-security-in-your-application)). Must be a literal boolean (no parameter nor environment variable). |

### logs

See [process executions & logs](04-process_executions_and_logs.md) for the whole logging mechanism.

| Key                      | Type     | Default                                   | Description                                                                                                                                  |
|--------------------------|----------|-------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `store_in_database`      | `bool`   | `true`                                    | Enable/disable the storage of process logs in database (`log_record` table). The log file is always written.                               |
| `database_level`         | `string` | `Debug` on `dev` environment, else `Info` | Minimum Monolog level of the log records stored in database.                                                                                 |
| `file_level`             | `string` | `Debug` on `dev` environment, else `Info` | Minimum Monolog level of the log records written in the execution log file.                                                                  |
| `report_increment_level` | `string` | `Warning`                                 | Minimum Monolog level from which a log record increments the execution report (one counter per level, e.g. `Warning: 3`, `Error: 1`). Only records passing `file_level` are counted. |
| `database_max_days`      | `int`, `null` | `null`                               | Days after which the log records are deleted from database by the [`cleverage:ui-process:purge-logs`](07-console_commands.md#cleverageui-processpurge-logs) command. `null`: kept. Can be overridden by process. |
| `file_max_days`          | `int`, `null` | `null`                               | Days after which the log files are deleted by the [`cleverage:ui-process:purge-logs`](07-console_commands.md#cleverageui-processpurge-logs) command. `null`: kept. Can be overridden by process. |

See [log retention](04-process_executions_and_logs.md#log-retention).

Levels are Monolog level names (case-insensitive): `Debug`, `Info`, `Notice`, `Warning`, `Error`, `Critical`,
`Alert`, `Emergency`; `database_level` and `file_level` also accept the level values (`100` to `600`). An invalid level
fails when the container is compiled (`Invalid configuration for path "clever_age_ui_process.logs.<key>"`). A level
given by an environment variable is only checked at runtime, by Monolog.

### design

| Key         | Type     | Default                                 | Description                                                                                                |
|-------------|----------|-----------------------------------------|------------------------------------------------------------------------------------------------------------|
| `logo_path` | `string` | `bundles/cleverageuiprocess/logo.jpg`   | Path, relative to the public directory, of the logo displayed in the UI navigation. The default logo requires `bin/console assets:install`. |

### notification

Notification of the end of the process executions, with [symfony/notifier](https://symfony.com/doc/current/notifier.html)
(optional dependency). Each key can be overridden by process. See [notifications](09-notifications.md).

| Key          | Type       | Default                          | Description                                                                                                                         |
|--------------|------------|----------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `enabled`    | `bool`     | `false`                          | Notify the end of the process executions. `true` requires `symfony/notifier`.                                                       |
| `statuses`   | `string[]` | `[failed, finish_with_report]`   | Ends of process executions to notify: `failed`, `finish_with_report` (finished with log levels counted in its report), `finish` (finished without them), `stopped` (stopped from the UI). |
| `channels`   | `string[]` | `[]`                             | Notifier channels, e.g. `chat/slack`, `email`. Empty: the `channel_policy` of the notifier, by importance.                           |
| `recipients` | `array[]`  | `[]`                             | Recipients, with an `email` and/or a `phone`. Empty: the `admin_recipients` of the notifier.                                         |

### process_execution

See [stopping a process execution](04-process_executions_and_logs.md#stopping-a-process-execution) and
[not responding executions](04-process_executions_and_logs.md#not-responding-executions).

| Key                  | Type  | Default | Description                                                                                                                                          |
|----------------------|-------|---------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `heartbeat_interval` | `int` | `5`     | Seconds between two updates of the heartbeat of a running process execution, which also read its stop request (minimum `1`).                     |
| `stale_after`        | `int` | `900`   | Seconds without heartbeat after which a started process execution is displayed as not responding, and can be marked as failed (minimum `1`). Keep it above the longest task call of your processes. |

Container parameters
--------------------

| Parameter                               | Default                                   | Description |
|-----------------------------------------|-------------------------------------------|-------------|
| `cleverage_ui_process.upload_directory` | `%kernel.project_dir%/var/storage/uploads` | Directory where files uploaded from the launch form or the [HTTP API](06-http_api.md) are stored before being passed as process input. |
| `cleverage_ui_process.delete_uploaded_files` | `true` | Delete the uploaded file once the process has been executed (successfully or not), by the Messenger handler or by the HTTP API when the process is not queued. Only the files of `cleverage_ui_process.upload_directory` are deleted. Set it to `false` to keep them, e.g. when a process stores the path of its input. |
| `upload_directory`                      | `%cleverage_ui_process.upload_directory%` | Deprecated since 3.3, removed in 4.0: use `cleverage_ui_process.upload_directory`. When your application still sets it, its value is used (unless `cleverage_ui_process.upload_directory` is also set), with a deprecation. |

It can be overridden in your application:

```yaml
# config/services.yaml
parameters:
    cleverage_ui_process.upload_directory: '%kernel.project_dir%/var/imports'
```

Configuration added to other bundles
------------------------------------

The bundle configures other bundles, so that it works out of the box. The Messenger configuration is prepended: your
application configuration overrides it. The other ones are added after your application configuration, so they override
it. Each one is skipped when the bundle is not enabled.

| Bundle               | Configuration                                                                                                                                                                                   |
|----------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `monolog`            | `pb_ui_file` and `pb_ui_orm` handlers (with their `*_filter` handlers) listening to the `cleverage_process` and `cleverage_process_task` channels. See [process executions & logs](04-process_executions_and_logs.md). |
| `doctrine_migrations` | Migrations path `CleverAge\UiProcessBundle\Migrations`.                                                                                                                                        |
| `framework`          | Messenger `execute_process` transport (`doctrine://default`, no retry) and routing of `ProcessExecuteMessage` to it. See [messenger](08-messenger.md).                                            |
| `security`           | `process_user_provider` user provider and the `main` firewall, unless `security.configure_firewall` is `false`. See [users & security](03-users_and_security.md).                              |
