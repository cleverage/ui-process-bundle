Users & security
================

Security configuration
----------------------

The bundle prepends the following configuration to the `security` extension:

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
                login_path: process_login
                check_path: process_login
                enable_csrf: true
            logout:
                path: process_logout
                target: process_login
                clear_site_data: '*'
```

So your application firewall must be named `main`. The only required application configuration is a password
hasher for the `User` entity (the Symfony default configuration is enough):

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
```

The UI pages are protected by `#[IsGranted]` attributes, no `access_control` rule is required.

The login form is protected by a CSRF token (token id `authenticate`, parameter `_csrf_token`): the Symfony CSRF
protection must be enabled (`framework.csrf_protection`, enabled by default when the session is). If you override the
login template, keep the `_csrf_token` field (the `csrf_token_intention` variable passed by `LoginController`).

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

Routes are imported with `@CleverAgeUiProcessBundle/config/routes/*.yaml` (see [installation](../index.md#import-routes)).

| Route name                   | Path                            | Description                                                                                  |
|------------------------------|---------------------------------|----------------------------------------------------------------------------------------------|
| `process`                    | `/process`                      | EasyAdmin dashboard, redirects to the executions list. CRUD pages are sub-routes of it.     |
| `process_login`              | `/process/login`                | Login form.                                                                                  |
| `process_logout`             | `/process/logout`               | Logout.                                                                                      |
| `process_list`               | `/process/list`                 | List of public processes.                                                                    |
| `process_launch`             | `/process/launch?process=<code>` | Launch a process (confirmation, form or direct, see [launch modes](02-process_ui_options.md#launch-modes)). |
| `http_process_execute`       | `/http/process/execute` (POST)  | [HTTP API](06-http_api.md).                                                                  |
