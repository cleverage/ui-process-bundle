## Documentation

- [Prerequisite](#prerequisite)
- [Installation](#installation)
- [Quick setup](#quick-setup)
- Cookbooks
    - [Launch a CSV import from a form with file upload](cookbooks/form_file_upload.md)
    - [Schedule a recurring process](cookbooks/scheduled_process.md)
    - [Launch a process through the HTTP API](cookbooks/http_api_launch.md)
- Reference
    - [Bundle configuration](reference/01-bundle_configuration.md)
    - [Process UI options](reference/02-process_ui_options.md)
    - [Users & security](reference/03-users_and_security.md)
    - [Process executions & logs](reference/04-process_executions_and_logs.md)
    - [Scheduler](reference/05-scheduler.md)
    - [HTTP API](reference/06-http_api.md)
    - [Console commands](reference/07-console_commands.md)
    - [Messenger & asynchronous execution](reference/08-messenger.md)
- [Troubleshooting](troubleshooting.md)
- [CleverAge/ProcessBundle documentation](https://github.com/cleverage/process-bundle/blob/main/docs/index.md)

## Prerequisite

CleverAge/ProcessBundle must be [installed](https://github.com/cleverage/process-bundle/blob/main/docs/01-quick_start.md#installation).

This bundle provides a web UI, built on [EasyAdmin](https://symfony.com/bundles/EasyAdminBundle/current/index.html),
on top of the process bundle. It does not provide any process task. Its features are:
- a process list, where each public process can be launched (with a confirmation modal, a form to set input and
  context, or directly),
- the history of every process execution (whatever the way it was launched: UI, console, HTTP, scheduler), with its
  status, duration, report and logs (stored in database and in a log file),
- a scheduler to run processes periodically (cron or periodical expressions),
- user management (login form, roles, API tokens),
- an HTTP endpoint to launch a process from another application.

It relies on Doctrine ORM (users, executions, logs, schedules), Symfony Messenger (asynchronous execution), Symfony
Scheduler and Monolog.

## Installation

Make sure Composer is installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Open a command console, enter your project directory and install it using composer:

```bash
composer require cleverage/ui-process-bundle
```

Remember to add the following line to `config/bundles.php` (not required if Symfony Flex is used)

```php
CleverAge\UiProcessBundle\CleverAgeUiProcessBundle::class => ['all' => true],
```

## Quick setup

### Import routes

```yaml
# config/routes.yaml
ui-process-bundle:
    resource: '@CleverAgeUiProcessBundle/config/routes/*.yaml'
```

This imports the bundle controllers and the EasyAdmin routes (do not import `easyadmin.routes` a second time).
See [routes](reference/03-users_and_security.md#routes).

### Security

The bundle automatically configures a user provider and the `main` firewall (login form, logout, API token
authenticator). You only need a password hasher for the bundle `User` entity, which is the Symfony default:

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
```

See [users & security](reference/03-users_and_security.md).

### Database

Supported databases: **MySQL / MariaDB** and **PostgreSQL**.

The bundle registers its own Doctrine migrations (`CleverAge\UiProcessBundle\Migrations`), written for these two
platforms (on another platform, e.g. SQLite, they do nothing: create the schema with `doctrine:schema:update`). Run
them, then create a first user:

```bash
bin/console doctrine:migrations:migrate
bin/console cleverage:ui-process:user-create admin@example.com 'my-password'
```

### Assets

The default logo is served from the bundle `public/` directory:

```bash
bin/console assets:install
```

### Workers

Processes launched from the UI, the HTTP API (queued) or the scheduler are executed asynchronously with Symfony
Messenger. Keep the following workers running (see [messenger](reference/08-messenger.md)):

```bash
bin/console messenger:consume execute_process
bin/console messenger:consume scheduler_cron
```

Now you can access the UI via http://your-domain.com/process.

### Configuration

The bundle works without configuration. Every option is described in
[bundle configuration](reference/01-bundle_configuration.md):

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    security:
        roles: ['ROLE_ADMIN']
    logs:
        store_in_database: true
        database_level: Info # Debug on dev environment
        file_level: Info # Debug on dev environment
        report_increment_level: Warning
    design:
        logo_path: 'bundles/cleverageuiprocess/logo.jpg'
```

The way each process is displayed and launched from the UI is configured in the process itself, under
`options.ui` (see [process UI options](reference/02-process_ui_options.md)):

```yaml
clever_age_process:
    configurations:
        app.import_products:
            entry_point: read
            options:
                ui:
                    source: ERP
                    target: Database
                    ui_launch_mode: form
                    entrypoint_type: file
            tasks:
                read:
                    # ...
```
