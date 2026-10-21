Users & security
================

Security configuration
----------------------

The bundle adds the following configuration to the `security` extension:

```yaml
security:
    providers:
        process_user_provider:
            entity:
                class: CleverAge\UiProcessBundle\Entity\User
                property: email
    firewalls:
        main:
            provider: process_user_provider
            custom_authenticator: [cleverage_ui_process.security.http_process_execution_authenticator]
            form_login:
                login_path: cleverage_ui_process_login
                check_path: cleverage_ui_process_login
            logout:
                path: cleverage_ui_process_logout
                target: cleverage_ui_process_login
                clear_site_data: '*'
```

This configuration is added **after** the application one, so it overrides it: the `provider`, `form_login` and `logout`
of your `main` firewall are replaced by the bundle ones (e.g. the `provider: users_in_memory` of the default Symfony
recipe), and the API token authenticator is added to it. So your application firewall must be named `main`, and it is
dedicated to the UI. To keep the control of your security configuration, disable it, see
[configuring the security in your application](#configuring-the-security-in-your-application).

With this configuration, the only required application configuration is a password
hasher for the `User` entity (the Symfony default configuration is enough):

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
```

The UI pages are protected by `#[IsGranted]` attributes, no `access_control` rule is required.

### Configuring the security in your application

Set `clever_age_ui_process.security.configure_firewall` to `false` (it must be a literal boolean, not a parameter nor an
environment variable): the bundle no longer adds any `security` configuration, neither the user provider nor the
firewall. Configure them in your application, on the firewall and with the provider of your choice. The UI requires:

- a user provider of the `CleverAge\UiProcessBundle\Entity\User` entity, by `email`;
- a firewall covering the UI routes, with a login form on the `cleverage_ui_process_login` route and the logout on the
  `cleverage_ui_process_logout` route;
- the `cleverage_ui_process.security.http_process_execution_authenticator` authenticator on the firewall covering the
  `cleverage_ui_process_http_execute` route, for the [HTTP API](06-http_api.md).

For instance, with the same behaviour as the bundle configuration on a firewall dedicated to the UI:

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    security:
        configure_firewall: false
```

```yaml
# config/packages/security.yaml
security:
    providers:
        process_user_provider:
            entity:
                class: CleverAge\UiProcessBundle\Entity\User
                property: email
    firewalls:
        process:
            pattern: ^/(process|http/process)
            provider: process_user_provider
            custom_authenticator: [cleverage_ui_process.security.http_process_execution_authenticator]
            form_login:
                login_path: cleverage_ui_process_login
                check_path: cleverage_ui_process_login
            logout:
                path: cleverage_ui_process_logout
                target: cleverage_ui_process_login
                clear_site_data: '*'
        main:
            # ... your application firewall
```

The `pattern` depends on the prefix of the [routes](#routes) (here the default ones).

Roles
-----

| Role         | Granted to                                                          | Access                                                                                                          |
|--------------|---------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `ROLE_USER`  | Every user (always added by `User::getRoles()`)                     | Dashboard, process list and launch, executions, logs, scheduler.                                               |
| `ROLE_ADMIN` | Users created with the console command, or selected in the UI form  | Everything above, plus the "Users" menu (users list, creation, edition, deletion and API token generation).    |

The roles selectable in the user edit form are configured with `clever_age_ui_process.security.roles` (default
`['ROLE_ADMIN']`, see [bundle configuration](01-bundle_configuration.md#security)). Add your own roles there if your
application uses them, e.g. with a `role_hierarchy`.

Users
-----

Users are stored in the `process_user` table (`CleverAge\UiProcessBundle\Entity\User`):

| Field                   | Description                                                                                                  |
|-------------------------|--------------------------------------------------------------------------------------------------------------|
| `email`                 | Unique, used as login.                                                                                       |
| `password`              | Hashed password.                                                                                             |
| `firstname`, `lastname` | Optional.                                                                                                    |
| `roles`                 | Extra roles (`ROLE_USER` is implicit).                                                                       |
| `timezone`              | Optional: timezone used to display dates in the UI (defaults to the PHP default timezone).                  |
| `locale`                | Optional: locale of the UI for this user (the bundle ships `en` and `fr` translations).                     |
| `token`                 | Hashed API token, see [HTTP API](06-http_api.md#authentication).                                             |

The first user must be created with the [`cleverage:ui-process:user-create`](07-console_commands.md#cleverageui-processuser-create)
command. Then administrators can manage users from the "Users > User List" menu.

### API token

From the user edit page, the "Generate token" action creates a new random token for this user. The token is displayed
**once** in a flash message, and only its hash is stored: keep it in a secured area. Generating a new token replaces
the previous one. It is used to authenticate on the [HTTP API](06-http_api.md).

Routes
------

Routes are imported with `@CleverAgeUiProcessBundle/config/routes/*.yaml`, or
`@CleverAgeUiProcessBundle/config/routes/controllers.yaml` when the EasyAdmin routes are already imported, as done by the
Symfony Flex recipe (see [installation](../index.md#import-routes)).

| Route name                          | Former name (deprecated) | Path                             | Description                                                                                  |
|-------------------------------------|--------------------------|----------------------------------|----------------------------------------------------------------------------------------------|
| `cleverage_ui_process`              | `process`                | `/process`                       | EasyAdmin dashboard, redirects to the executions list. CRUD pages are sub-routes of it.     |
| `cleverage_ui_process_login`        | `process_login`          | `/process/login`                 | Login form.                                                                                  |
| `cleverage_ui_process_logout`       | `process_logout`         | `/process/logout`                | Logout.                                                                                      |
| `cleverage_ui_process_list`         | `process_list`           | `/process/list`                  | List of public processes.                                                                    |
| `cleverage_ui_process_launch`       | `process_launch`         | `/process/launch?process=<code>` | Launch a process (confirmation, form or direct, see [launch modes](02-process_ui_options.md#launch-modes)). |
| `cleverage_ui_process_http_execute` | `http_process_execute`   | `/http/process/execute` (POST)   | [HTTP API](06-http_api.md).                                                                  |

The routes of the CRUD pages are generated by EasyAdmin from the dashboard route name:
`cleverage_ui_process_<crud>_<action>` (e.g. `cleverage_ui_process_process_execution_index`), formerly
`process_<crud>_<action>`.

The former route names are deprecated aliases since 3.3 (`config/routes/deprecated_aliases.yaml`), removed in 4.0:
generating a URL with them, or opening a `?routeName=<former name>` URL of the dashboard, still works and triggers a
deprecation. They are not defined when only `controllers.yaml` is imported (Symfony Flex recipe): import
`deprecated_aliases.yaml` too if your application still uses them.

The EasyAdmin routes (the dashboard and its CRUD pages) are generated by the `easyadmin.routes` loader imported by
`config/routes/easyadmin.yaml`. This loader generates the routes of **every** dashboard, including the ones of your
application: a `prefix` set on the import of the bundle routes also applies to the dashboards of your application, and
`easyadmin.routes` must not be imported a second time.
