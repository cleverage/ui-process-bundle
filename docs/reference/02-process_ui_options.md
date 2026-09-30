Process UI options
==================

The way a process is displayed and launched from the UI is configured in the process definition itself, under the
`ui` key of its `options` (see the
[process definition](https://github.com/cleverage/process-bundle/blob/main/docs/reference/01-process_definition.md)).

YAML Configuration
------------------

```yaml
clever_age_process:
    configurations:
        <process_code>:
            public: <true|false>
            entry_point: <task_code>
            options:
                ui:
                    source: <string>
                    target: <string>
                    ui_launch_mode: <modal|form|null>
                    entrypoint_type: <text|file>
                    default:
                        input: <mixed>
                        context:
                            - key: <string>
                              value: <string>
                    constraints: <array of constraints>
            tasks:
                # ...
```

Every key is optional: a process without `options.ui` is displayed and launched with a confirmation modal. Unknown
keys, or invalid values, raise an options resolver error when the process list is displayed.

Options
-------

| Key               | Type             | Default                            | Description                                                                                                                         |
|-------------------|------------------|------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `source`          | `string`, `null` | `null`                             | Free label describing where the data comes from. Displayed in the process list and in the executions list.                         |
| `target`          | `string`, `null` | `null`                             | Free label describing where the data goes. Displayed in the process list and in the executions list.                               |
| `ui_launch_mode`  | `string`, `null` | `modal`                            | Behaviour of the "Launch" (rocket) action of the process list. See [launch modes](#launch-modes).                                   |
| `entrypoint_type` | `string`         | `text`                             | `text` or `file`: type of the `input` field of the launch form (`form` launch mode only).                                          |
| `default`         | `array`          | `{input: null, context: []}`       | Initial values of the launch form (`form` launch mode only). See [default values](#default-values).                                 |
| `constraints`     | `array`          | `[]`                               | Symfony validation constraints applied to the launch form (`form` launch mode only). See [constraints](#constraints).              |
| `run`             | `mixed`          | `null`                             | Accepted for backward compatibility, not used.                                                                                      |

Process visibility
------------------

Only public processes (`public: true`, the default of the process bundle) are displayed in the process list, can be
selected in the [scheduler](05-scheduler.md) and can be launched through the [HTTP API](06-http_api.md). Private
processes are still recorded in the [executions](04-process_executions_and_logs.md) when they run (e.g. as
sub-processes or from the console).

Launch modes
------------

| `ui_launch_mode`   | Behaviour of the "Launch" action                                                                               |
|--------------------|----------------------------------------------------------------------------------------------------------------|
| `modal` (default)  | Opens a confirmation modal, then queues the process without input nor context.                                 |
| `form`             | Opens a form to set the process input and context, then queues the process.                                    |
| `~` (`null`)       | Queues the process immediately, without any confirmation.                                                      |

In every mode, the process is not executed by the web request: a message is dispatched to the `execute_process`
Messenger transport and a worker runs it (see [messenger](08-messenger.md)). The UI adds the email of the connected
user to the process context, under the `execution_user` key.

### Launch form

The launch form (`/process/launch?process=<process_code>`) contains:
- an `input` field: a text field when `entrypoint_type` is `text`, a file upload field when it is `file`. It is
  required if the process defines an `entry_point`.
- a `context` collection of key/value pairs (both are required when a line is added). Each pair becomes a process
  context value, like `-c key:value` on the `cleverage:process:execute` console command.

When `entrypoint_type` is `file`, the uploaded file is saved to the `upload_directory` directory (see
[bundle configuration](01-bundle_configuration.md#container-parameters)) with a random name keeping the original
extension, and its absolute path is passed as process input. The file is not removed after the execution.

Default values
--------------

`default.input` pre-fills the input field. It is ignored when `entrypoint_type` is `file` (a file field cannot be
pre-filled).

`default.context` is a list of `key`/`value` pairs (both required) pre-filling the context collection. The user can
change, remove or add pairs before launching.

```yaml
options:
    ui:
        ui_launch_mode: form
        entrypoint_type: text
        default:
            input: 'Steven King'
            context:
                - key: firstname
                  value: Steven
                - key: lastname
                  value: King
```

Constraints
-----------

`constraints` uses the same syntax as the [ValidatorTask](https://github.com/cleverage/process-bundle/blob/main/docs/reference/tasks/validator_task.md)
of the process bundle (and as Symfony YAML validation mapping): a list of constraint short names (or FQCN) with their
options. Constraints are applied to the whole form data, which is an array with two keys:
- `input`: the input string, or the `UploadedFile` object when `entrypoint_type` is `file`,
- `context`: the context as an associative array (`key => value`).

While a constraint is violated, the process is not launched and the form is displayed again with the violation
messages.

```yaml
options:
    ui:
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
                          - File:
                                extensions: [csv]
                      context:
                          - Collection:
                                fields:
                                    delimiter:
                                        - Choice:
                                              choices: [',', ';']
                                              message: 'delimiter context must be , or ;. {{ value }} given.'
```

Note that the `Collection` constraint rejects missing and extra fields by default: use `allowExtraFields: true` (or
`allowMissingFields: true`) if users may add other context values.

See the [form with file upload](../cookbooks/form_file_upload.md) cookbook for a complete example.
