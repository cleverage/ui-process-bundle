Bundle configuration
====================

YAML Configuration
------------------

Every key is optional. Default values are shown below.

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    security:
        roles: ['ROLE_ADMIN']
    logs:
        store_in_database: true
        database_level: Info # Debug when kernel.environment is "dev"
        file_level: Info # Debug when kernel.environment is "dev"
        report_increment_level: Warning
    design:
        logo_path: 'bundles/cleverageuiprocess/logo.jpg'
```

Options
-------

### security

| Key     | Type       | Default          | Description                                                                                                                                       |
|---------|------------|------------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `roles` | `string[]` | `['ROLE_ADMIN']` | Roles that can be assigned to a user in the UI user edit form. `ROLE_USER` is always granted to every user (see [users & security](03-users_and_security.md)). |

### logs

See [process executions & logs](04-process_executions_and_logs.md) for the whole logging mechanism.

| Key                      | Type     | Default                                   | Description                                                                                                                                  |
|--------------------------|----------|-------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `store_in_database`      | `bool`   | `true`                                    | Enable/disable the storage of process logs in database (`log_record` table). The log file is always written.                               |
| `database_level`         | `string` | `Debug` on `dev` environment, else `Info` | Minimum Monolog level of the log records stored in database.                                                                                 |
| `file_level`             | `string` | `Debug` on `dev` environment, else `Info` | Minimum Monolog level of the log records written in the execution log file.                                                                  |
| `report_increment_level` | `string` | `Warning`                                 | Minimum Monolog level from which a log record increments the execution report (one counter per level, e.g. `Warning: 3`, `Error: 1`). Only records passing `file_level` are counted. |

Levels are Monolog level names (case-insensitive): `Debug`, `Info`, `Notice`, `Warning`, `Error`, `Critical`,
`Alert`, `Emergency`.

### design

| Key         | Type     | Default                                 | Description                                                                                                |
|-------------|----------|-----------------------------------------|------------------------------------------------------------------------------------------------------------|
| `logo_path` | `string` | `bundles/cleverageuiprocess/logo.jpg`   | Path, relative to the public directory, of the logo displayed in the UI navigation. The default logo requires `bin/console assets:install`. |

Container parameters
--------------------

| Parameter          | Default                                        | Description                                                                                                                       |
|--------------------|------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `upload_directory` | `%kernel.project_dir%/var/storage/uploads`     | Directory where files uploaded from the launch form or the [HTTP API](06-http_api.md) are stored before being passed as process input. |

It can be overridden in your application:

```yaml
# config/services.yaml
parameters:
    upload_directory: '%kernel.project_dir%/var/imports'
```

Configuration added to other bundles
------------------------------------

The bundle prepends some configuration to other bundles, so that it works out of the box:

| Bundle               | Configuration                                                                                                                                                                                   |
|----------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `monolog`            | `pb_ui_file` and `pb_ui_orm` handlers (with their `*_filter` handlers) listening to the `cleverage_process` and `cleverage_process_task` channels. See [process executions & logs](04-process_executions_and_logs.md). |
| `doctrine_migrations` | Migrations path `CleverAge\UiProcessBundle\Migrations`.                                                                                                                                        |
| `framework`          | Messenger `execute_process` transport (`doctrine://default`, no retry) and routing of `ProcessExecuteMessage` to it. See [messenger](08-messenger.md).                                            |
| `security`           | `process_user_provider` user provider and the `main` firewall. See [users & security](03-users_and_security.md).                                                                              |
