
## CleverAge/ProcessUIBundle
A simple UX for cleverage/processbundle using EasyAdmin

**Installation**
* Import routes
```yaml  
#config/routes.yaml  
processui:  
 resource: '@CleverAgeProcessUiBundle/src/Controller' type: attribute  
```  
* Run doctrine migration
* Create an user using cleverage:process-ui:user-create console.

Now you can access Process UI via http://your-domain.com/process

# Features
### Launch process via http request
You can launch a process via http post request
First you need to generate a token via UI User edit form. The ProcessUi generate for you a auth token (keep it in secured area, it will display once).

It' all, now you can launch a process via http post request

***Curl sample***
```
curl --location 'https://localhost:8080/http/process/execute?code=demo.die' \
--header 'Authorization: Bearer 3da8409b5f5b640fb0c43d68e8ac8d23' \
--form 'input=@"/file.csv"' \
--form 'context[context_1]="FOO"' \
--form 'context[context_2]="BAR"'
```
* Query string code parameter must be a valid process code
* Header Autorization: Bearer is the previously generated token
* input could be string or file representation
* context you can pass multiple context values


### Scheduler
You can schedule process execution via UI using cron expression (*/5 * * * *) or periodical triggers (5 seconds)
For more details about cron expression and peridical triggers visit https://symfony.com/doc/6.4/scheduler.html#cron-expression-triggers and https://symfony.com/doc/6.4/scheduler.html#periodical-triggers

In order to make sheduler process working be sure the following command is running
```
bin/console messenger:consume scheduler_cron
```
See more details about ***messenger:consume*** command in consume message section

# Consume Messages
Symfony messenger is used in order to run process via UI or schedule process

*To consume process launched via UI make sure the following command is running*
```
bin/console messenger:consume execute_process
```

*To consume scheduled process make sure the following command is running*
```
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