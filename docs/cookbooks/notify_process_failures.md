Notify the failures of the processes on Slack and by email
=========================================================

This recipe sends a Slack message when a process fails or ends with warnings, and an email to the sales team at the
end of every run of their daily import.

Install the notifier and the Slack bridge:

```bash
composer require symfony/notifier symfony/slack-notifier
```

Configure the notifier: the failures (`high` importance) go to Slack and to the admin recipients by email, the
warnings (`medium` importance) to Slack only:

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        chatter_transports:
            slack: '%env(SLACK_DSN)%' # e.g. slack://TOKEN@default?channel=CHANNEL
        channel_policy:
            high: ['chat/slack', 'email']
            medium: ['chat/slack']
            low: ['chat/slack']
        admin_recipients:
            - { email: 'ops@example.com' }
```

The `email` channel requires `symfony/mailer` (and its `MAILER_DSN`).

Enable the notifications for every process, with the default statuses (`failed`, `finish_with_report`):

```yaml
# config/packages/clever_age_ui_process.yaml
clever_age_ui_process:
    notification:
        enabled: true
```

Then override it for the processes needing it:

```yaml
# config/packages/process/app.daily_import.yaml
clever_age_process:
    configurations:
        app.daily_import:
            options:
                notification:
                    statuses: [failed, finish_with_report, finish] # every run
                    channels: ['email']
                    recipients:
                        - { email: 'sales@example.com' }
            tasks:
                # ...
        app.cache_warmup:
            options:
                notification:
                    enabled: false
            tasks:
                # ...
```

- A failed process sends a Slack message and an email to `ops@example.com`, e.g. `Process "app.purge_authors"
  failed`, with the error, the duration and the log file of the execution.
- A process ending with warnings (`Warning` or higher logs, see `logs.report_increment_level`) sends a Slack message,
  e.g. `Process "app.purge_authors" finished with reported logs`, with its report (`Warning: 3, Error: 1`).
- Every run of `app.daily_import` sends an email to `sales@example.com`, `app.cache_warmup` is never notified.

See [notifications](../reference/09-notifications.md) for the whole configuration and the
`ProcessExecutionEndedEvent` to send your own notifications.
