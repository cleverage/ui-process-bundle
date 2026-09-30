Launch a CSV import from a form with file upload
===============================================

This recipe lets a back-office user upload a CSV file from the UI, choose its delimiter, and import it. The launch
form validates the file extension and the delimiter before the process is queued.

```yaml
# config/packages/process/app.import_csv.yaml
clever_age_process:
    configurations:
        app.import_csv:
            description: 'Import an uploaded CSV file'
            help: 'Ex: bin/console cleverage:process:execute app.import_csv --input=/path/to/file.csv -c delimiter:";"'
            entry_point: log_file # Required: the uploaded file path is sent to this task
            options:
                ui:
                    source: CSV upload
                    target: Logs
                    ui_launch_mode: form
                    entrypoint_type: file
                    default:
                        context:
                            - key: delimiter
                              value: ','
                    constraints:
                        - Collection:
                              fields:
                                  input:
                                      - NotNull: ~
                                      - File:
                                            extensions: [csv]
                                  context:
                                      - Collection:
                                            fields:
                                                delimiter:
                                                    - Choice:
                                                          choices: [',', ';']
                                                          message: 'delimiter context must be , or ;. {{ value }} given.'
            tasks:
                log_file:
                    service: '@CleverAge\ProcessBundle\Task\Reporting\LoggerTask'
                    options:
                        level: info
                        message: 'Read file'
                        context: ['input']
                    outputs: [read]

                read:
                    service: '@CleverAge\ProcessBundle\Task\File\Csv\InputCsvReaderTask'
                    options:
                        delimiter: '{{ delimiter }}' # Contextualized option, set from the launch form
                    outputs: [log_line]

                log_line:
                    service: '@CleverAge\ProcessBundle\Task\Reporting\LoggerTask'
                    options:
                        level: info
                        message: 'Read line'
                        context: ['input']
```

How it works:
- `ui_launch_mode: form` makes the "Launch" action of the process list open a form instead of a confirmation modal
  (see [launch modes](../reference/02-process_ui_options.md#launch-modes)).
- `entrypoint_type: file` turns the `input` field into a file upload. As the process has an `entry_point`, the field
  is required. The uploaded file is saved in the `upload_directory` directory and its path is sent to the
  `log_file` task (see [launch form](../reference/02-process_ui_options.md#launch-form)).
- `default.context` pre-fills a `delimiter` line in the context collection
  (see [default values](../reference/02-process_ui_options.md#default-values)). It is injected into the
  `InputCsvReaderTask` through the `{{ delimiter }}` contextualized option, exactly like `-c delimiter:";"` on the
  console.
- `constraints` validate the whole form data (`input` and `context`): the process is not queued while the file is not
  a CSV or the delimiter is not `,` or `;` (see [constraints](../reference/02-process_ui_options.md#constraints)).
  As the `Collection` constraint rejects extra fields, users cannot add other context values: add
  `allowExtraFields: true` to allow them.
- On submit, a message is sent to the `execute_process` transport, so a `messenger:consume execute_process` worker
  must be running (see [messenger](../reference/08-messenger.md)). The connected user email is added to the context
  as `execution_user`.
- The execution, its report and its logs are then available in "Process > Executions"
  (see [process executions & logs](../reference/04-process_executions_and_logs.md)).

Uploaded files are not removed after the execution: add a cleaning step at the end of the process (e.g. a
[FileRemoverTask](https://github.com/cleverage/process-bundle/blob/main/docs/reference/tasks/file_remover_task.md)) or purge the `upload_directory` directory periodically.

The same process can also be launched through the [HTTP API](../reference/06-http_api.md), with a file upload:

```bash
curl --location 'https://your-domain.com/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--form 'code="app.import_csv"' \
--form 'input=@/path/to/file.csv' \
--form 'context[delimiter]=";"'
```

Note that the launch form `constraints` are not applied to HTTP API calls.
