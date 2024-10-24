## Prerequisite

CleverAge/ProcessBundle must be [installed](https://github.com/cleverage/process-bundle/blob/main/docs/01-quick_start.md#installation.

## Installation

Make sure Composer is installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Open a command console, enter your project directory and install it using composer:

```bash
composer require cleverage/ui-process-bundle
```

Remember to add the following line to config/bundles.php (not required if Symfony Flex is used)

```php
CleverAge\UiProcessBundle\CleverAgeUiProcessBundle::class => ['all' => true],
```

## Import routes

```yaml
#config/routes.yaml
ui-process:
  resource: '@CleverAgeUiProcessBundle/Resources/config/routes.yaml'
```
* Run doctrine migration
* Create an user using cleverage:ui-process:user-create console.

Now you can access Process UI via http://your-domain.com/process

## Indexing logs

You can index logs line into database to perform search on ****Process > History**** page.
See configuration section.

When indexation is enabled you can perform it async.

```yaml
#config/messenger.yaml
framework:
  messenger:
    transports:
      log_index: 'doctrine://default'

    routing:
      CleverAge\UiProcessBundle\Message\LogIndexerMessage: log_index
```

Then you have to consume messages by running (use a supervisor to keep consumer alive)
```
bin/console messenger:consume log_index --memory-limit=64M
```

See official `symfony/messenger` component documentations for more informations https://symfony.com/doc/current/messenger.html

## Manual EasyAdmin integration

### Integrate CrudController

Of course, you can integrate UiProcess CRUD into your own easy admin Dashboard
```php
    public function configureMenuItems(): iterable
    {
        /* ... your configuration */
        yield MenuItem::linkToCrud('History', null, ProcessExecution::class);
    }
```

### Configuration

```yaml
#config/cleverage_process_ui.yaml
cleverage_ui_process:
  index_logs:
  enabled: false
  level: ERROR #Minimum log level to index. Allowed values are DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
```

## Reference

_TODO_
