## CleverAge/ProcessUIBundle
A simple UX for cleverage/processbundle using EasyAdmin

**Installation**
* Import routes
```yaml
#config/routes.yaml
process-ui:
  resource: '@CleverAgeProcessUiBundle/Resources/config/routes.yaml'
```
* Run doctrine migration
* Create an user using cleverage:process-ui:user-create console.

Now you can access Process UI via http://your-domain.com/process

**Indexing logs**

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
      CleverAge\ProcessUiBundle\Message\LogIndexerMessage: log_index
```

Then you have to consume messages by running (use a supervisor to keep consumer alive)
```
bin/console messenger:consume log_index --memory-limit=64M
```

See official symfony/messenger component documentations for more informations https://symfony.com/doc/current/messenger.html

**Integrate CrudController**

Of course you can integrate ProcessUI CRUD into your own easy admin Dashboard
```php
    public function configureMenuItems(): iterable
    {
        /* ... your configuration */
        yield MenuItem::linkToCrud('History', null, ProcessExecution::class);
    }
```

**Configuration**
```yaml
clever_age_process_ui:
  index_logs:
  enabled: false
  level: ERROR #Minimum log level to index. Allowed values are DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
```