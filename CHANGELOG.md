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
