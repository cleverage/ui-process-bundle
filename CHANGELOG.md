Latest
------

## Changes
* [#138](https://github.com/cleverage/ui-process-bundle/issues/138) Installation documentation: describe the Symfony Flex recipe, which registers the bundle, imports the bundle controllers routes (`config/routes/clever_age_ui_process.yaml`, the EasyAdmin routes being imported by the EasyAdmin recipe) and creates `config/packages/clever_age_ui_process.yaml`, and the manual setup without Flex.

v3.3
------

## Changes
* Require `cleverage/process-bundle` `^5.4`.
* Please follow [UPGRADE.md v3.3](UPGRADE.md#v33)
* [#196](https://github.com/cleverage/ui-process-bundle/issues/196) Prefix the global names of the bundle, as required by the Symfony best practices for bundles: new `cleverage_ui_process.upload_directory` parameter, the `upload_directory` parameter is deprecated (when the application still sets it, its value is used with a deprecation, unless the new parameter is also set; new internal `UploadDirectoryParameterPass`); the Twig functions `log_label`, `log_translation`, `log_css_class`, `get_last_execution_date`, `get_process_source`, `get_process_target`, `resolve_ui_options` and the Twig filter `md5` are prefixed with `cleverage_ui_process_`, the former names are deprecated. The former names will be removed in 4.0. Update documentation and UPGRADE.md, add tests.
* [#194](https://github.com/cleverage/ui-process-bundle/issues/194) The tables and columns of the entities have explicit names, those created by the migrations of the bundle: the schema no longer depends on the Doctrine naming strategy of the application (the `underscore_number_aware` strategy is no longer a prerequisite; with the Doctrine default one, the indexes referenced missing columns). Same names with the `underscore` / `underscore_number_aware` strategies, no new migration. Update documentation, add tests.
* [#190](https://github.com/cleverage/ui-process-bundle/issues/190) Add aliases named after their class to `ProcessExecutionManager`, `ProcessConfigurationsManager`, `ProcessExecutionRepository` and `ProcessScheduleRepository` (as the Monolog handlers): they can be autowired. Update documentation, add tests.
* [#192](https://github.com/cleverage/ui-process-bundle/issues/192) The controllers and the HTTP value resolver are defined explicitly, without autowiring nor autoconfiguration (Symfony best practices for bundles, as [cleverage/process-bundle#149](https://github.com/cleverage/process-bundle/issues/149)): same arguments, tags and calls. The `$context` binding of the controllers no longer registers the `AdminContextFactory` service for the `AdminContext $context` arguments of the EasyAdmin actions (never used: EasyAdmin resolves the context first). Add tests.
* [#187](https://github.com/cleverage/ui-process-bundle/issues/187) Composer: require PHP `^8.2` instead of `>=8.2` (PHP 9 is no longer accepted before being tested), remove the authorization of the `symfony/flex` plugin, which is not required (the CI installs it globally), as [cleverage/process-bundle#319](https://github.com/cleverage/process-bundle/issues/319).
* [#181](https://github.com/cleverage/ui-process-bundle/issues/181) Fix the deprecations revealed by the tests, which now fail on deprecations: `ProcessExecutionRepository::getLastProcessExecution()` passes the `\SortDirection` enum to `orderBy()` when doctrine/orm accepts it (3.7 or later, where a string direction is deprecated, [#152](https://github.com/cleverage/ui-process-bundle/issues/152)); `ProcessExecutionRepository::hasLogs()` counts the log records without an arbitrary join (`WITH` deprecated by doctrine/orm 3.7, same result); the date filters of the log records (`createdAt`) and of the process executions (`startDate`) use the `single_text` widget (not configuring it is deprecated by Symfony 6.4, already the default since Symfony 7.0); `User::eraseCredentials()` is marked `#[\Deprecated]`, as required by Symfony 7.3 (with Symfony 6.4 and PHP 8.4 or later, which still call it, set `security.erase_credentials: false` to avoid the deprecation).
* [#180](https://github.com/cleverage/ui-process-bundle/issues/180) Define the public API (Symfony BC promise, as [cleverage/process-bundle#279](https://github.com/cleverage/process-bundle/issues/279)): `ProcessExecutionManager`, `ProcessExecutionRepository`, `ProcessScheduleRepository`, `ProcessExecuteMessage`, `ProcessExecutionNotification` and `CleverAgeUiProcessBundle` are marked `@final since 3.3`, they will be `final` in 4.0: extending them triggers a deprecation with the Symfony `DebugClassLoader`. The entities stay open (overriding them: [#37](https://github.com/cleverage/ui-process-bundle/issues/37)). The infrastructure classes are marked `@internal`: the extension, `Configuration`, the commands, the controllers, the EasyAdmin fields and filters, the form types, the Messenger handlers, the Monolog handlers, `HttpProcessExecuteValueResolver`, `HttpProcessExecutionAuthenticator`, `ProcessExecutionStopChecker`, `CronScheduler`, `ProcessEventSubscriber` and the Twig extensions, runtimes and components.
* [#179](https://github.com/cleverage/ui-process-bundle/issues/179) `CleverAgeUiProcessExtension::findServices()` loads the service definition files with a glob pattern instead of a `Finder` (same files, as [cleverage/process-bundle#271](https://github.com/cleverage/process-bundle/issues/271)). Add tests.
* [#172](https://github.com/cleverage/ui-process-bundle/issues/172) Prefix the route names of the bundle with `cleverage_ui_process_`, as required by the Symfony best practices for bundles: `cleverage_ui_process_login`, `cleverage_ui_process_logout`, `cleverage_ui_process_list`, `cleverage_ui_process_launch`, `cleverage_ui_process_http_execute`, and the dashboard `cleverage_ui_process`, so that the routes generated by EasyAdmin for the CRUD pages are `cleverage_ui_process_<crud>_<action>`. The former names (`process`, `process_<crud>_<action>`, `process_login`, `process_logout`, `process_list`, `process_launch`, `http_process_execute`) are deprecated route aliases (new `config/routes/deprecated_aliases.yaml`, loaded by the documented import, and internal `DeprecatedEasyAdminRouteAliasLoader` for the routes generated by EasyAdmin), removed in 4.0; `?routeName=<former name>` URLs keep working. Document that `easyadmin.routes` generates the routes of every dashboard. Update documentation and UPGRADE.md, add tests.
* [#166](https://github.com/cleverage/ui-process-bundle/issues/166) Doctrine ORM 2: the entities have readonly properties (since v3.2), which the legacy proxies of ORM 2 cannot handle (`Cannot unset readonly property ... from scope Proxies\__CG__\...` when an entity proxy is loaded). The container compilation now fails with an explicit message when ORM 2 is used without `doctrine.orm.enable_lazy_ghost_objects: true` (new internal `CheckDoctrineLazyGhostObjectsPass`); nothing changes with ORM 3. Update documentation, add tests.
* [#164](https://github.com/cleverage/ui-process-bundle/issues/164) Require `symfony/polyfill-php83`, `symfony/polyfill-php84` and `symfony/polyfill-php85`: the functions of PHP 8.3 to 8.5 (`json_validate()`, `array_find()`, `array_first()`...) can be used while supporting PHP 8.2 (see `CONTRIBUTING.md`). Rector is configured for PHP 8.2 (was 8.5, which allowed it to introduce PHP 8.3+ functions without polyfill) with the `PHP_POLYFILLS` set (`withPhpSets()` without argument), it does not change the code. `array_last()` replaces `end()` in a test. Tests: lazy ghost proxies with Doctrine ORM 2 (now installed by the lowest dependencies job), as configured by the Symfony recipe: the legacy proxies cannot unset the readonly properties of the entities.
* [#158](https://github.com/cleverage/ui-process-bundle/issues/158) CI: test the lowest versions allowed by `composer.json` (`--prefer-lowest`, PHP 8.2). Require `symfony/scheduler` ^6.4.44 || ^7.4.17 || ^8.1.5: the `every` expressions validation (`EveryExpressionValidator`) relies on the `PeriodicalTrigger` rejecting the intervals that do not move the run date forward or resolve to zero seconds (older versions loop forever on e.g. "yesterday", and fail with a `TypeError` on an invalid expression). `ProcessExecutionDurationFilter`: the number of seconds is cast to an integer and inlined in the DQL instead of being bound as a parameter (#111): with DBAL 3, the SQLite platform puts the `SECOND` intervals inside a string literal, so the parameter was never bound ("column index out of range" error on SQLite with DBAL 3).
* [#156](https://github.com/cleverage/ui-process-bundle/issues/156) `LogRecord::contextIsEmpty()` (deprecated since v3.1, use `hasContextInfo()`) triggers a deprecation at runtime with `trigger_deprecation()` (`symfony/deprecation-contracts`, now required). Add tests.
* [#154](https://github.com/cleverage/ui-process-bundle/issues/154) Add a `.gitattributes`: the development files (`.docker/`, `.github/`, `tests/`, quality tools and PHPUnit configurations, `Makefile`) are no longer part of the archive installed by Composer (`dist`), remove the unused `package.json` (Webpack Encore leftover of the Symfony skeleton: no `webpack.config.js`, no `assets/`).
* [#149](https://github.com/cleverage/ui-process-bundle/issues/149) Clean up composer.json: remove the unused `symfony/dotenv`, `symfony/runtime`, `ext-ctype` and `ext-iconv` requirements (applications relying on the bundle to install `symfony/dotenv` / `symfony/runtime` must require them, as the Symfony skeleton does); replace `doctrine/common` by `doctrine/collections` (only `ArrayCollection` is used); require `twig/twig`, `symfony/twig-bundle`, `psr/log`, `doctrine/persistence`, `doctrine/migrations` and `symfony/event-dispatcher-contracts`, used directly but only installed transitively; add `twig/extra-bundle` and `symfony/property-access` (used by the tests) to the dev requirements.
* [#150](https://github.com/cleverage/ui-process-bundle/issues/150) Require `symfony/monolog-bundle` and `monolog/monolog`, used directly by the bundle (log handlers, `monolog` configuration prepended, `monolog.logger` tags) but only installed through `cleverage/process-bundle`, which may stop requiring MonologBundle (cleverage/process-bundle#269).

## Fixes
* [#185](https://github.com/cleverage/ui-process-bundle/issues/185) `Version20261005120000` migration (#97), MySQL (MariaDB shares the same code): `foreign_key_checks` is disabled around the change of `log_record.process_execution_id` to `NOT NULL`, the foreign key is kept as is. Without a strict SQL mode (neither `STRICT_TRANS_TABLES` nor `STRICT_ALL_TABLES`), MySQL makes the column `NOT NULL` with `ALGORITHM=COPY`, which is refused on a foreign key column while `foreign_key_checks` is enabled: the migration failed with "1832 Cannot change column 'process_execution_id': used in a foreign key constraint 'FK_8ECECC333DAC0075'". Same resulting schema. If the migration failed, run the migrations again after the update; if the workaround of #185 was applied (`doctrine:migrations:version ... --add`), there is nothing to do. CI: the migrations are also run on MySQL without a strict SQL mode.
* [#174](https://github.com/cleverage/ui-process-bundle/issues/174) Long-running workers (Messenger, scheduler): `ProcessExecutionManager` implements `ResetInterface` (`kernel.reset` tag), its current and last process executions are reset between two messages; `DoctrineProcessHandler` drops the log records not written at the end of the previous execution on `reset()` (`kernel.reset` tag). `ProcessEventSubscriber` forgets the ended process execution (and closes its log file) even when it cannot be saved: the next process executed by the worker was attached to it. Add tests.
* [#173](https://github.com/cleverage/ui-process-bundle/issues/173) Fix the hard-coded URLs: the page displayed after the login is generated from the dashboard route (was `/process`: wrong when the routes are imported with a `prefix`, or the application is served from a sub-directory), and the logo URL is built by the Asset component (base path, `framework.assets.base_urls`, version strategy; absolute URLs kept as is; was `/<logo_path>`). New `ProcessDashboardController` constructor argument (`Symfony\Component\Asset\Packages`, autowired). Require `symfony/asset`, used directly. Add tests.
* [#171](https://github.com/cleverage/ui-process-bundle/issues/171) Configuration: `config:dump-reference clever_age_ui_process` and `debug:config clever_age_ui_process` no longer fail (`CleverAgeUiProcessExtension::getConfiguration()` gives the kernel environment to `Configuration`, so the PHP config builder is generated too); the options are documented with `info()`. The `logs.database_level`, `logs.file_level` and `logs.report_increment_level` options are validated when the container is compiled, an invalid level no longer makes every process execution fail at runtime (environment variables are still allowed, checked at runtime). Update documentation, add tests.
* [#169](https://github.com/cleverage/ui-process-bundle/issues/169) Security: the configuration added by the bundle to the `security` extension (`process_user_provider` user provider, provider, login form, logout and API token authenticator of the `main` firewall) overrides the application one, it can now be disabled with the new `security.configure_firewall` option (default `true`, same behaviour as before; `false`: no `security` configuration is added, the application configures the provider and the firewall, e.g. a firewall dedicated to the UI). The `monolog`, `doctrine_migrations` and `security` configurations are skipped when the bundle is not enabled, instead of a `LogicException`. The documentation no longer says that these configurations are prepended (only the Messenger one is). Update documentation, add tests.

v3.2
------

## Changes
* [#142](https://github.com/cleverage/ui-process-bundle/issues/142) Scheduler: a schedule can be disabled (`ProcessSchedule::$enabled`, default `true`, switch in the schedules list; `Version20261013120000` migration adding `process_schedule.enabled`, the existing schedules are enabled). Disabled schedules are not loaded by the `scheduler_cron` worker; when a schedule is due, `CronProcessMessageHandler` reads it again from the database (new optional `$repository` and `$logger` arguments, `ProcessScheduleRepository::findFresh()`), so that a schedule disabled or deleted after the worker has started is not run, and its current input and context are used. Enabling a schedule again still requires restarting the worker. Add documentation and tests.
* [#143](https://github.com/cleverage/ui-process-bundle/issues/143) Stop a running process execution from the UI: "Stop the process" action (POST, with confirmation) on the `started` executions, recording a stop request (`ProcessExecution::$stopRequestedAt`). The new `ProcessExecutionStopChecker` (a stop checker of cleverage/process-bundle#266, tag `cleverage_process.stop_checker`) reads it with DBAL before the task executions, at most every `process_execution.heartbeat_interval` seconds (new bundle configuration, default `5`): the process stops before its next task execution, with the new `stopped` status (`ProcessExecutionStatus::Stopped`, "Stop requested" displayed until then). New `stopped` notification status (`NotificationTrigger::Stopped`, not notified by default). Requires `cleverage/process-bundle` ^5.3. Add documentation and tests.
* [#144](https://github.com/cleverage/ui-process-bundle/issues/144) Detect the process executions whose process has been killed: the `ProcessExecutionStopChecker` updates the heartbeat of the running execution (`ProcessExecution::$heartbeatAt`); a `started` execution without activity for more than `process_execution.stale_after` seconds (new bundle configuration, default `900`) is displayed as "Not responding", and can be marked as failed ("Mark as failed" action, POST, with confirmation; its end date is its last activity; its process is not stopped). It can still be stopped: a process blocked in a long task call stops when the call returns. New `ProcessExecutionCrudController` optional `$staleAfter` constructor argument.
* [#143](https://github.com/cleverage/ui-process-bundle/issues/143) [#144](https://github.com/cleverage/ui-process-bundle/issues/144) `Version20261014120000` migration adding `process_execution.stop_requested_at` and `process_execution.heartbeat_at` (nullable).
* [#147](https://github.com/cleverage/ui-process-bundle/issues/147) Log retention: new `cleverage:ui-process:purge-logs` command (with `--dry-run`) deleting the log records (`log_record`, by batches) older than `logs.database_max_days` days and the log files (`<code>/<uuid>.log`) last modified more than `logs.file_max_days` days ago (new bundle configuration, default `null`: kept), both overridable by the new `logs` option of each process (`ProcessConfigurationsManager::getLogsOptions()`). The process executions are kept. Add documentation (including how to give the space back to the database after the first purge) and tests.

v3.1.1
------

## Changes
* [#18](https://github.com/cleverage/ui-process-bundle/issues/18) PHPStan level 10 (was 8), on `src` and `tests`: `LogProcessFilter::new()` `$label` typed as `TranslatableInterface|string|false|null` (the type of `setLabel()`), `LogRecord::$context` documented as `array<mixed>` (the Monolog context), conditional return type of `ProcessExecution::getReport()`.

## Fixes
* [#135](https://github.com/cleverage/ui-process-bundle/issues/135) HTTP API: a `context` given as a JSON string that does not decode to an array (`"1"`, `"true"`, `"\"abc\""`, `"null"`, `""`) is rejected with a `422` (`Context must be a JSON object or array.`); it gave a 500 (`TypeError`), whose message exposed the server paths with a synchronous execution. Add tests.

v3.1
------

## Changes
* [#81](https://github.com/cleverage/ui-process-bundle/issues/81) Add missing tests: functional tests of the UI and the HTTP API on a test application (SQLite, in-memory Messenger), unit tests of every class (DI, command, migrations, entities, managers, message handlers, scheduler, Monolog handlers, forms, EasyAdmin fields and filters, Twig, validators); `memory_limit` set to 512M for the test suite.
* [#83](https://github.com/cleverage/ui-process-bundle/issues/83) Remove `UploadAndExecuteAction` (route `process_upload_and_execute`), `ProcessUploadFileType` and `ProcessConfigurationValueResolver`: the action was broken and unreachable, file uploads are handled by `LaunchAction` (`entrypoint_type: file`).
* [#84](https://github.com/cleverage/ui-process-bundle/issues/84) Add `LogRecord::hasContextInfo()`, deprecate the misnamed `LogRecord::contextIsEmpty()` (it returns `true` when the context is not empty). Add tests.
* [#85](https://github.com/cleverage/ui-process-bundle/issues/85) `ProcessHandler`: default report increment level aligned on the bundle configuration (`Warning`); declare the `symfony/ux-twig-component` dependency. Add tests.
* [#48](https://github.com/cleverage/ui-process-bundle/issues/48) Executions list: the duration is rendered by the `@CleverAgeUiProcess/admin/field/duration.html.twig` template, so that its format can be changed by overriding the template instead of `ProcessExecutionCrudController` (the `$translator` argument of its constructor is no longer used: deprecated, will be removed in 4.0). Add tests.
* [#62](https://github.com/cleverage/ui-process-bundle/issues/62) Notify the end of the process executions with Symfony Notifier (optional `symfony/notifier` dependency): `notification` bundle configuration (`enabled`, default `false`; `statuses` among `failed`, `finish_with_report` (finished with log levels counted in its report), `finish`, default `[failed, finish_with_report]`; `channels`, default the notifier channel policy; `recipients`, default the notifier admin recipients), overridden by the `notification` option of each process. Add the `ProcessExecutionEndedEvent`, dispatched when a top-level process execution has ended and has been saved (new optional `$eventDispatcher` argument of `ProcessEventSubscriber`). Add documentation and a cookbook. Add tests.
* [#129](https://github.com/cleverage/ui-process-bundle/issues/129) CI: `migrations` job running the migrations on MySQL, MariaDB and PostgreSQL, with DBAL 3 and 4 (`migrate`, `doctrine:schema:validate`, `migrate first`, `migrate` again); console of the test application (`tests/App/bin/console`), database URL overridable with `DATABASE_URL` (SQLite by default).

## Fixes
* [#89](https://github.com/cleverage/ui-process-bundle/issues/89) `ProcessConfigurationsManager`: resolve the `ui.default` option with a normalizer instead of nested options defined with `setDefault()` (deprecated since symfony/options-resolver 7.3, removed in 8.0). With Symfony 8, a process launched with the UI form (`ui_launch_mode: form`) without `ui.default` no longer fails (`Cannot use object of type Closure as array`), and `ui.default` is validated again. Add tests.
* [#91](https://github.com/cleverage/ui-process-bundle/issues/91) `LoginController`: pass `error` and `last_username` (`AuthenticationUtils`) to the login template, so that a failed login displays the error message and keeps the email. Test updated.
* [#95](https://github.com/cleverage/ui-process-bundle/issues/95) `DoctrineProcessHandler`: detach the written `LogRecord` entities after each flush (the Monolog records were detached instead), so that the identity map no longer grows during long processes; `LogRecord::$processExecution` cascade reduced from `all` to `persist`, so that detaching a log record does not detach the current process execution (which would then be inserted again). Add tests.
* [#97](https://github.com/cleverage/ui-process-bundle/issues/97) Align the mapping and the schema created by the migrations: `ProcessSchedule::$input` mapped as `VARCHAR(255)` as created by the migrations (was `TEXT`); `Version20261005120000` migration (MySQL / MariaDB, PostgreSQL) making `log_record.process_execution_id` `NOT NULL` (log records without process execution are deleted) and `process_execution.context` nullable, as in the mapping.
* [#99](https://github.com/cleverage/ui-process-bundle/issues/99) Fix the migrations on PostgreSQL (they could not create a working schema): id columns created as identity columns (the sequences were not used: inserts failed with the IDENTITY generation), `process_schedule` created with the PostgreSQL syntax (`AUTO_INCREMENT` failed). PostgreSQL support documented.
* [#103](https://github.com/cleverage/ui-process-bundle/issues/103) When a process task clears the entity manager (shared with the UI bundle, e.g. `ClearEntityManagerTask`), the current process execution is attached again before being saved and before writing the logs: it was inserted again (an execution left `started` without logs, a duplicate one with the final status and all the logs). Add `ProcessExecutionManager::getManagedProcessExecution()` and `ProcessExecutionRepository::getManaged()`. When a process task closes the entity manager (Doctrine closes it after an error during a flush), it is reset (`ManagerRegistry::resetManager()`, new optional `ProcessExecutionRepository` argument) before saving the execution and writing the logs: the execution was left `started`, without end date nor logs. Add tests.
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
* [#127](https://github.com/cleverage/ui-process-bundle/issues/127) `Version20261008120000` migration (MySQL / MariaDB, PostgreSQL): remove the `(DC2Type:...)` column comments created by the migrations when the type comments are not used (always with DBAL 4, `disable_type_comments` with DBAL 3): the schema was always reported as out of sync; with DBAL 3 using the type comments, they are kept. `UiOptions` PHPDoc: types instead of string literals (`'run': ?bool`, context rows `int|string`).

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
