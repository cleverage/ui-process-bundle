Latest
------

## Changes
* [#81](https://github.com/cleverage/ui-process-bundle/issues/81) Add missing tests: functional tests of the UI and the HTTP API on a test application (SQLite, in-memory Messenger), unit tests of every class (DI, command, migrations, entities, managers, message handlers, scheduler, Monolog handlers, forms, EasyAdmin fields and filters, Twig, validators); `memory_limit` set to 512M for the test suite.
* [#83](https://github.com/cleverage/ui-process-bundle/issues/83) Remove `UploadAndExecuteAction` (route `process_upload_and_execute`), `ProcessUploadFileType` and `ProcessConfigurationValueResolver`: the action was broken and unreachable, file uploads are handled by `LaunchAction` (`entrypoint_type: file`).
* [#84](https://github.com/cleverage/ui-process-bundle/issues/84) Add `LogRecord::hasContextInfo()`, deprecate the misnamed `LogRecord::contextIsEmpty()` (it returns `true` when the context is not empty). Add tests.
* [#85](https://github.com/cleverage/ui-process-bundle/issues/85) `ProcessHandler`: default report increment level aligned on the bundle configuration (`Warning`); declare the `symfony/ux-twig-component` dependency. Add tests.

## Fixes
* [#89](https://github.com/cleverage/ui-process-bundle/issues/89) `ProcessConfigurationsManager`: resolve the `ui.default` option with a normalizer instead of nested options defined with `setDefault()` (deprecated since symfony/options-resolver 7.3, removed in 8.0). With Symfony 8, a process launched with the UI form (`ui_launch_mode: form`) without `ui.default` no longer fails (`Cannot use object of type Closure as array`), and `ui.default` is validated again. Add tests.
* [#91](https://github.com/cleverage/ui-process-bundle/issues/91) `LoginController`: pass `error` and `last_username` (`AuthenticationUtils`) to the login template, so that a failed login displays the error message and keeps the email. Test updated.
* [#95](https://github.com/cleverage/ui-process-bundle/issues/95) `DoctrineProcessHandler`: detach the written `LogRecord` entities after each flush (the Monolog records were detached instead), so that the identity map no longer grows during long processes; `LogRecord::$processExecution` cascade reduced from `all` to `persist`, so that detaching a log record does not detach the current process execution (which would then be inserted again). Add tests.
* [#97](https://github.com/cleverage/ui-process-bundle/issues/97) Align the mapping and the schema created by the migrations: `ProcessSchedule::$input` mapped as `VARCHAR(255)` as created by the migrations (was `TEXT`); `Version20261005120000` migration (MySQL / MariaDB, PostgreSQL) making `log_record.process_execution_id` `NOT NULL` (log records without process execution are deleted) and `process_execution.context` nullable, as in the mapping.
* [#99](https://github.com/cleverage/ui-process-bundle/issues/99) Fix the migrations on PostgreSQL (they could not create a working schema): id columns created as identity columns (the sequences were not used: inserts failed with the IDENTITY generation), `process_schedule` created with the PostgreSQL syntax (`AUTO_INCREMENT` failed). PostgreSQL support documented.
* [#103](https://github.com/cleverage/ui-process-bundle/issues/103) When a process task clears the entity manager (shared with the UI bundle, e.g. `ClearEntityManagerTask`), the current process execution is attached again before being saved and before writing the logs: it was inserted again (an execution left `started` without logs, a duplicate one with the final status and all the logs). Add `ProcessExecutionManager::getManagedProcessExecution()` and `ProcessExecutionRepository::getManaged()`. Add tests.
* [#105](https://github.com/cleverage/ui-process-bundle/issues/105) `LogProcessFilter` ("Process" filter of the logs): the process codes condition is added with `andWhere()` (`where()` replaced the search clause, whose parameters stayed bound: 500 when searching with this filter), and the "is not" comparison is applied (it was ignored: the logs of the process were displayed). Add tests.
* [#107](https://github.com/cleverage/ui-process-bundle/issues/107) `CronScheduler`: an error on a process schedule (e.g. `every 0 seconds`, accepted by the validator but not by the Symfony Scheduler) is logged and the schedule skipped; it skipped all the next schedules. Add tests.
* [#109](https://github.com/cleverage/ui-process-bundle/issues/109) `ProcessExecutionCrudController::downloadLogFile()`: 404 when the log file no longer exists (`file_get_contents()` warning: 500). Add tests.
* [#111](https://github.com/cleverage/ui-process-bundle/issues/111) `ProcessExecutionDurationFilter`: the filter values are bound as DQL parameters (they were concatenated in the DQL; not exploitable, the form field is numeric). Add tests.
* [#113](https://github.com/cleverage/ui-process-bundle/issues/113) HTTP API, synchronous execution: the error message is followed by the id of the process execution (`(process execution: <id>)`), to find its logs in the UI; add `ProcessExecutionManager::getLastProcessExecution()`. Add tests.
* [#117](https://github.com/cleverage/ui-process-bundle/issues/117) Schedule validators: `every` expressions checked as the Symfony Scheduler does (`PeriodicalTrigger`): expressions such as `0 seconds`, `yesterday` or `monday` were accepted but stopped the `scheduler_cron` worker, ISO 8601 durations (`PT1H`) were refused; message fixed ("is not a valid \"every\" expression"). A "hashed" cron expression is refused (500). No `TypeError` on `null`. Add tests.
* [#119](https://github.com/cleverage/ui-process-bundle/issues/119) Launch form: a context row without key is left out of the context (reported by its `NotBlank` constraint): it gave a 500 when the process has constraints on the context, and a PHP 8.5 deprecation (`null` array offset). Add tests.
* [#121](https://github.com/cleverage/ui-process-bundle/issues/121) `ListAction` / `LaunchAction`: accessed directly (`/process/list`, `/process/launch`), redirected to the dashboard (`/process?routeName=...`) instead of a 500. Add tests.
* [#123](https://github.com/cleverage/ui-process-bundle/issues/123) HTTP API: the parameters of the query string are also read without body, and `queue` is read from the query string (the form data takes precedence). Add tests.
* [#125](https://github.com/cleverage/ui-process-bundle/issues/125) `ProcessSchedule::getContext()` decodes a JSON string as an array; `User::getRoles()` no longer duplicates `ROLE_USER`; `symfony/expression-language` declared (used by `Assert\When`); the `underscore_number_aware` Doctrine naming strategy prerequisite is documented. Add tests.

v3.0.2
------

## Fixes
* [GHSA-r3m7-69c2-2vmp](https://github.com/cleverage/ui-process-bundle/security/advisories/GHSA-r3m7-69c2-2vmp) ProcessScheduleCrudController: require `ROLE_USER`, so that the scheduler pages require an authenticated user again (since EasyAdmin 5, the `#[IsGranted]` of the dashboard does not apply to the CRUD routes); ProcessExecuteController: same attribute, as defense in depth. Add a test checking that every controller is protected.

v3.0.1
------

## Changes
* [#78](https://github.com/cleverage/ui-process-bundle/issues/78) Update quality stack: use Rector `withComposerBased()` sets (removed `SYMFONY_64` / `PHPUNIT_100` sets), declare used Symfony packages and PHPUnit range in composer.json, apply quality tools fixes
* [#80](https://github.com/cleverage/ui-process-bundle/issues/80) Add missing documentations: reference pages for bundle configuration, process UI options, users & security, process executions & logs, scheduler, HTTP API, console commands and messenger, cookbooks and troubleshooting. Harmonize and fix existing documentation.

## Fixes
* [GHSA-3f6c-2rqw-2rx3](https://github.com/cleverage/ui-process-bundle/security/advisories/GHSA-3f6c-2rqw-2rx3) HttpProcessExecutionAuthenticator: read the route from the request attributes, so that the `http_process_execute` endpoint requires a valid token again

v3.0
------

## Changes

* [#65](https://github.com/cleverage/ui-process-bundle/issues/65) Add support for PHP 8.5 and Symfony 8, update dependencies. Update PHPUnit configuration to version 12 schema and adjust coverage settings.
* [#65](https://github.com/cleverage/ui-process-bundle/issues/65) Mise à jour de la version de doctrine-fixtures-bundle pour inclure la compatibilité avec la version 4
* [#69](https://github.com/cleverage/ui-process-bundle/issues/69) Upgrade to EasyAdmin V5

## Fixes

* [#63](https://github.com/cleverage/ui-process-bundle/issues/63) Use preprendExtensionConfig instead of loadFromExtension to allow messenger config for execute_process transport
* [#67](https://github.com/cleverage/ui-process-bundle/issues/67) Fix Incorrect datetime persisted in the database log records

## BC break

* [#65](https://github.com/cleverage/ui-process-bundle/issues/65) Remove support for PHP 8.1 and Symfony 7.3
* Please follow [UPGRADE.md v3.0](UPGRADE.md#v30)

v2.3
------

## Changes

* [#60](https://github.com/cleverage/ui-process-bundle/issues/60) Upgrade to Symfony 7.3 & PHP 8.4

## Fixes

* [#57](https://github.com/cleverage/ui-process-bundle/issues/57) add arguments to pass username and password to cleverage:ui-process:user-create
* [#60](https://github.com/cleverage/ui-process-bundle/issues/60) Fix php version to >=8.1 according to cleverage/process-bundle
* [#60](https://github.com/cleverage/ui-process-bundle/issues/60) Fix PHP 8.1 restrictions

v2.2
------

## Changes

* [#54](https://github.com/cleverage/ui-process-bundle/issues/54) When Launch process via http request, add queue parameter which define if the process should be queued (default) or directly run

v2.1.1
------

## Fixes

* [#52](https://github.com/cleverage/ui-process-bundle/issues/52) Fix ProcessScheduleRepository definition to be bundled compliant

v2.1
------

## Fixes

* [#42](https://github.com/cleverage/ui-process-bundle/issues/42) composer require dragonmantank/cron-expression because CronExpressionTrigger needs it
* [#40](https://github.com/cleverage/ui-process-bundle/issues/40) Fix localisation issues
* [#45](https://github.com/cleverage/ui-process-bundle/issues/45) Implement store_in_database & [database|file]_level configuration. Update documentation with full configuration.


## Changes

* [#34](https://github.com/cleverage/ui-process-bundle/issues/34) Improve process launch using http call.
* [#33](https://github.com/cleverage/ui-process-bundle/issues/33) Add duration filter on Process Execution Crud.
* [#47](https://github.com/cleverage/ui-process-bundle/issues/47) Add Troubleshooting section on documentation

v2.0.2
------

## Fixes

* [#29](https://github.com/cleverage/ui-process-bundle/issues/29) HttpProcessExecutionAuthenticator is not used
* [#30](https://github.com/cleverage/ui-process-bundle/issues/30) Run process via http post request to http_process_execute does not work

## Changes

* [#25](https://github.com/cleverage/ui-process-bundle/issues/25) UX tweak: take all the width available
* [#27](https://github.com/cleverage/ui-process-bundle/issues/27) UX Tweak: make the process listing more consistant with the other cruds

v2.0.1
------

## Fixes

* [#21](https://github.com/cleverage/ui-process-bundle/issues/21) Fix report.html.twig templating.


v2.0
------

## BC breaks

* [#4](https://github.com/cleverage/ui-process-bundle/issues/4) Update composer : "doctrine/*" using same versions of doctrine-process-bundle. 
  Remove "sensio/framework-extra-bundle" & "symfony/flex". Update require-dev using "process-bundle" standard. Reinstall "symfony/debug-pack". 
  "symfony/*" from ^5.4 to ^6.4|^7.1 => Update changes on code.
* [#2](https://github.com/cleverage/ui-process-bundle/issues/2) Routes must be prefixed with the bundle alias  => `cleverage_ui_process`
* [#2](https://github.com/cleverage/ui-process-bundle/issues/2) Update services according to Symfony best practices. Services should not use autowiring or autoconfiguration. Instead, all services should be defined explicitly.
  Services must be prefixed with the bundle alias instead of using fully qualified class names => `cleverage_ui_process`
* [#3](https://github.com/cleverage/ui-process-bundle/issues/3) Rename process-ui-bundle to ui-process-bundle, 
  cleverage:process-ui:xxx to cleverage:ui-process:xxx, clever_age_process_ui to cleverage_ui_process and ProcessUi*** to UiProcess***

### Changes

* [#1](https://github.com/cleverage/ui-process-bundle/issues/1) Add Makefile & .docker for local standalone usage
* [#1](https://github.com/cleverage/ui-process-bundle/issues/1) Add rector, phpstan & php-cs-fixer configurations & apply it. Remove phpcs configuration.
* [#11](https://github.com/cleverage/ui-process-bundle/issues/11) Restrict "Download log file" and "Show logs stored in database" buttons visibility


v1.0.6
------

### Fixes

* Update ProcessExecutionCrudController.php. Avoid fatal error if no permission to display row

v1.0.5
------

### Fixes

* [#1](https://github.com/cleverage/processuibundle/issues/1) fix fatal error

v1.0.4
------

### Changes

* Only logs errors to level >= INFO

v1.0.3
------

### Changes

* Add search fields to the ProcessExecution Crud

v1.0.2
------

### Fixes

* Fix setSearchFields on ProcessCrudController

v1.0.1
------

### Changes

* Php-cs-fixer & phpstan rules applying. Update README

v1.0.0
------

* Initial release
