## Prerequisite

CleverAge/ProcessBundle must be [installed](https://github.com/cleverage/process-bundle/blob/main/docs/01-quick_start.md#installation.

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

## Configuration

### Import routes

```yaml
ui-process-bundle:
  resource: '@CleverAgeUiProcessBundle/src/Controller'
  type: attribute
```

### Doctrine ORM Configuration

* Run doctrine migration
* Create a user using `cleverage:ui-process:user-create` console.

Now you can access UI Process via http://your-domain.com/process

## Full configuration

```yaml
# config/packages/clever_age_ui_process.yaml

clever_age_ui_process:
  security:
    roles: ['ROLE_ADMIN'] # Roles displayed inside user edit form
  logs:
    store_in_database: true # enable/disable store log in database (log_record table)
    database_level: Debug (on dev env) or Info # min log level to store log record in database
    file_level: Debug (on dev env) or Info # min log level to store log record in file
    report_increment_level: Warning # min log level to increment process execution report
  design:
    logo_path: 'bundles/cleverageuiprocess/logo.jpg' # logo displayed in UI navigation toolbar
```

## Features

### Launch process via UI
From UI "Process List" menu entry you can run a process by clicking on "Rocket" action.
You can manage this behaviour by setting some ui option `ui_launch_mode`

|      Value      |                          UI behaviour                          |
|:---------------:|:--------------------------------------------------------------:|
| modal (default) | On click, open confirmation modal to confirm process execution | 
|      form       |    On click, open a form to set input and context execution    |
|      null       |              Run process without any confirmation              | 

### Launch process via http request
You can launch a process via http post request
First you need to generate a token via UI User edit form. The UiProcess generate for you a auth token (keep it in secured area, it will display once).

That's all, now you can launch a process via http post request

***Curl sample***
```bash
curl --location 'http://localhost/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--form 'input=@"/path/to/your/file.csv"' \
--form 'code="demo.dummy"' \
--form 'context="{\"foo\": \"bar\"}"'
```

```bash
curl --location 'http://localhost/http/process/execute' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer d641d254aed12733758a3a4247559868' \
--header 'Cookie: PHPSESSID=m8l9s5sniknv1b0jb798f8sri7; main_auth_profile_token=2f3d24' \
--data '{
"code": "demo.die",
"context": {"foo": "bar"}
}'
```

* Query string code parameter must be a valid process code
* Header Authorization: Bearer is the previously generated token
* input could be string or file representation
* context you can pass multiple context values


### Scheduler
You can schedule process execution via UI using cron expression (*/5 * * * *) or periodical triggers (5 seconds)
For more details about cron expression and periodical triggers visit 
https://symfony.com/doc/6.4/scheduler.html#cron-expression-triggers and https://symfony.com/doc/6.4/scheduler.html#periodical-triggers

In order to make scheduler process working be sure the following command is running
```bash
bin/console messenger:consume scheduler_cron
```
See more details about ***messenger:consume*** command in consume message section

## Consume Messages
Symfony messenger is used in order to run process via UI or schedule process

* To consume process launched via UI make sure the following command is running*
```bash
bin/console messenger:consume execute_process
```

* To consume scheduled process make sure the following command is running*
```bash
bin/console messenger:consume scheduler_cron
```
You can pass some options to messenger:consume command
```
Options:
  -l, --limit=LIMIT                  Limit the number of received messages
  -f, --failure-limit=FAILURE-LIMIT  The number of failed messages the worker can consume
  -m, --memory-limit=MEMORY-LIMIT    The memory limit the worker can consume
  -t, --time-limit=TIME-LIMIT        The time limit in seconds the worker can handle new messages
      --sleep=SLEEP                  Seconds to sleep before asking for new messages after no messages were found [default: 1]
  -b, --bus=BUS                      Name of the bus to which received messages should be dispatched (if not passed, bus is determined automatically)
      --queues=QUEUES                Limit receivers to only consume from the specified queues (multiple values allowed)
      --no-reset                     Do not reset container services after each message
```

It's recommended to use supervisor app or equivalent to keep command alive

***Sample supervisor configuration***
```
[program:scheduler]
command=php /var/www/html/bin/console messenger:consume scheduler_cron
autostart=false
autorestart=true
startretries=1
startsecs=1
redirect_stderr=true
stderr_logfile=/var/log/supervisor.scheduler-err.log
stdout_logfile=/var/log/supervisor.scheduler-out.log
user=www-data
killasgroup=true
stopasgroup=true

[program:process]
command=php /var/www/html/bin/console messenger:consume execute_process
autostart=false
autorestart=true
startretries=1
startsecs=1
redirect_stderr=true
stderr_logfile=/var/log/supervisor.process-err.log
stdout_logfile=/var/log/supervisor.process-out.log
user=www-data
killasgroup=true
stopasgroup=true
``` 

## Troubleshooting

### PHP Fatal error: Allowed memory size of xxx bytes exhausted

When `store_in_database` option is set, with lower value of `database_level` option, the process may generate many LogRecord.
On debug environment, profiling too much queries cause memory exhaustion. So, you can :
- Set `doctrine.dbal.profiling_collect_backtrace: false`
- Increase `memory_limit` in php.ini
- Set `clever_age_ui_process.logs.store_in_database: false` or improve value of `clever_age_ui_process.logs.database_level`
- Use `--no-debug` flag for `cleverage:process:execute`
