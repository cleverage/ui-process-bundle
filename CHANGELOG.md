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
