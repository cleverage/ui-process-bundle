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
                    form:
                        input:
                            type: Symfony\Component\Form\Extension\Core\Type\FileType
                            options:
                                label: CSV file
                                constraints:
                                    - NotNull: ~
                                    - File:
                                          # A CSV file is often detected as "text/plain" (semicolon delimiter, few lines): allow it explicitly
                                          extensions:
                                              csv: ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'text/x-comma-separated-values']
                        context:
                            delimiter:
                                type: Symfony\Component\Form\Extension\Core\Type\ChoiceType
                                default: ','
                                options:
                                    label: Delimiter
                                    choices: { 'Comma (,)': ',', 'Semicolon (;)': ';' }
                                    required: true   # a select that is not required has an empty choice
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
- `form.input` with a `FileType` type turns the `input` field into a file upload. As the process has an `entry_point`, the
  field is required (HTML attribute); its `NotNull` and `File` constraints are checked by the server: the process is
  not queued while no file is uploaded or the file is not a CSV, and the error is displayed under the field. The
  uploaded file is saved in the `upload_directory` directory and its path is sent to the `log_file` task (see
  [launch form](../reference/02-process_ui_options.md#launch-form)).
- `form.context.delimiter` (a `ChoiceType`) adds a `delimiter` select, pre-selected on `,`
  (see [form fields](../reference/02-process_ui_options.md#form-fields)). A value outside of the choices is refused by
  the field itself. The selected value is injected into the `InputCsvReaderTask` through the `{{ delimiter }}`
  contextualized option, exactly like `-c delimiter:";"` on the console.
- Users can still add free key/value pairs to the context: set `form.allow_extra_context: false` to forbid them.
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

Note that the launch form fields and constraints are not applied to HTTP API calls.
