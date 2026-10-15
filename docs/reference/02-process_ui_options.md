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
                    form:
                        input:                          # <null|field spec>
                            type: <alias|FQCN>          # default: text
                            options: <array>            # form type options, default: []
                            default: <mixed>            # initial value, default: null
                        context:
                            <key>: <null|field spec>    # same keys as "input"
                        allow_extra_context: <bool>     # default: true
                        constraints: <array of constraints>
                    # Deprecated since 3.3, use "form" instead
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

The `notification` key, next to `ui`, configures the [notification](09-notifications.md) of the end of the process
executions. The `logs` key, next to `ui`, configures the [log retention](04-process_executions_and_logs.md#log-retention)
of the process.

Options
-------

| Key               | Type             | Default                            | Description                                                                                                                         |
|-------------------|------------------|------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `source`          | `string`, `null` | `null`                             | Free label describing where the data comes from. Displayed in the process list and in the executions list.                         |
| `target`          | `string`, `null` | `null`                             | Free label describing where the data goes. Displayed in the process list and in the executions list.                               |
| `ui_launch_mode`  | `string`, `null` | `modal`                            | Behaviour of the "Launch" (rocket) action of the process list. See [launch modes](#launch-modes).                                   |
| `form`            | `array`          | `[]`                               | Fields, default values and constraints of the launch form (`form` launch mode only). See [form fields](#form-fields).              |
| `entrypoint_type` | `string`         | `text`                             | *Deprecated since 3.3, use `form.input.type`.* `text` or `file`: type of the `input` field of the launch form.                     |
| `default`         | `array`          | `{input: null, context: []}`       | *Deprecated since 3.3, use `form.input.default` and `form.context.<key>.default`.* See [default values](#default-values).          |
| `constraints`     | `array`          | `[]`                               | *Deprecated since 3.3, use `form.constraints`.* Constraints applied to the launch form. See [constraints](#constraints).            |
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
user to the process context, under the `execution_user` key: this value always wins, the launch form cannot set it.

### Launch form

The launch form (`/process/launch?process=<process_code>`) contains:
- an `input` field, of the type declared by `form.input` (a text field by default). It is required (HTML attribute)
  if the process defines an `entry_point`, unless `form.input.options.required` says otherwise.
- a `context` group: one field per key declared in `form.context`, followed (when `form.allow_extra_context` is
  `true`, the default) by a collection of free key/value pairs (both are required when a line is added). Each field
  or pair becomes a process context value, like `-c key:value` on the `cleverage:process:execute` console command.

The input can be of any type, but only a single uploaded file is saved: when the input is a `file` field, the
uploaded file is saved to the `upload_directory` directory (see
[bundle configuration](01-bundle_configuration.md#container-parameters)) with a random name keeping the original
extension, and its absolute path is passed as process input. The file is not removed after the execution.

Form fields
-----------

`form` declares the fields of the launch form:

```yaml
options:
    ui:
        ui_launch_mode: form
        form:
            input:
                type: file
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
                    type: choice
                    default: ','
                    options:
                        label: Delimiter
                        choices: { 'Comma (,)': ',', 'Semicolon (;)': ';' }
                dry_run:
                    type: checkbox
                    default: false
                    options: { required: false }
                comment: ~        # a text field without default value
            allow_extra_context: true
            constraints: []
```

| Key                        | Type                   | Default | Description                                                                                                      |
|----------------------------|------------------------|---------|------------------------------------------------------------------------------------------------------------------|
| `form.input`               | field spec, `null`     | `null`  | The `input` field. `null`: a text field (or the deprecated `entrypoint_type`).                                   |
| `form.context`             | map of field specs     | `[]`    | The declared context fields, by context key, in their display order. `null` is a text field without default.     |
| `form.allow_extra_context` | `bool`                 | `true`  | Whether free key/value pairs can be added to the context, for the keys that are not declared.                   |
| `form.constraints`         | `array`                | `[]`    | Constraints applied to the whole form data, see [constraints](#constraints).                                     |

### Field spec

A field spec (`form.input`, `form.context.<key>`) accepts exactly three keys:

| Key       | Type     | Default | Description                                                                                                    |
|-----------|----------|---------|----------------------------------------------------------------------------------------------------------------|
| `type`    | `string` | `text`  | A form type alias (see below) or the FQCN of a Symfony form type.                                               |
| `options` | `array`  | `[]`    | The options of the form type (`label`, `help`, `attr`, `choices`, `constraints`...).                            |
| `default` | `mixed`  | `null`  | The initial value of the field.                                                                                 |

The context keys must be valid form field names (letters, digits, `_`, `-` and `:`, starting with a letter, a digit
or `_`). A purely numeric key (e.g. `123`, even quoted in YAML) is not supported: PHP casts it to an integer. The
`extra` key (the free context pairs) and the `execution_user` key (the email of the connected user) are reserved.

The type aliases are the block prefixes of the Symfony core form types, and `entity`:

| Alias                                                                       | Form type                                                                       |
|-----------------------------------------------------------------------------|---------------------------------------------------------------------------------|
| `text`, `textarea`, `email`, `password`, `search`, `url`, `tel`, `hidden`   | `TextType`, `TextareaType`, `EmailType`, `PasswordType`, `SearchType`, `UrlType`, `TelType`, `HiddenType` |
| `integer`, `number`, `money`, `percent`, `range`                            | `IntegerType`, `NumberType`, `MoneyType`, `PercentType`, `RangeType`            |
| `checkbox`, `radio`, `choice`, `enum`                                       | `CheckboxType`, `RadioType`, `ChoiceType`, `EnumType`                           |
| `country`, `currency`, `language`, `locale`, `timezone`                     | `CountryType`, `CurrencyType`, `LanguageType`, `LocaleType`, `TimezoneType`     |
| `date`, `datetime`, `time`, `birthday`, `week`, `dateinterval`              | `DateType`, `DateTimeType`, `TimeType`, `BirthdayType`, `WeekType`, `DateIntervalType` |
| `color`, `file`, `uuid`, `ulid`, `collection`, `repeated`                   | `ColorType`, `FileType`, `UuidType`, `UlidType`, `CollectionType`, `RepeatedType` |
| `entity`                                                                    | `Symfony\Bridge\Doctrine\Form\Type\EntityType`                                 |

The aliases are case-sensitive. Any other type is a FQCN: it must implement `Symfony\Component\Form\FormTypeInterface`
and must not be a button type. Only the existence of the class is checked when the options are resolved: a form type
with dependencies must be a service (tag `form.type`, autoconfigured by default), and an option that the type does
not define (e.g. `choices` on a `text` field) fails when the form is built (500 on the launch page).

### Constraints of a field

`options.constraints` uses the same syntax as the [`constraints`](#constraints) option: a list of constraint short
names (or FQCN) with their options. A violation is displayed under the field.

The `extensions` option of the `File` constraint also checks the detected MIME type of the file. A CSV file is
often detected as `text/plain` (semicolon delimiter, single column, few lines): list the accepted MIME types per
extension (`extensions: { csv: ['text/csv', 'text/plain'] }`).

`options.required` is only the HTML `required` attribute (Symfony default: `true`, including for a `checkbox`, which
must then be checked): the server-side validation relies on `options.constraints` (e.g. `NotBlank`).

The `data`, `mapped`, `property_path` and `inherit_data` options are not supported (they break the mapping of the
context and of the default values): use `default` for the initial value.

### Default values of the fields

`default` is the initial value of the field, in the model format of its type:
- a `file` field cannot have a default value;
- for `date`, `datetime` and `time`, the YAML value is a string: set `options.input: string` (with the matching
  `options.input_format`), or keep a `null` default, otherwise the string cannot be transformed;
- for `choice`, the default value is one of the choices values;
- for `dateinterval`, the YAML value is a string: set `options.input: string` (an ISO 8601 duration such as `P1D`), or
  keep a `null` default.

### File fields

A `file` field is only supported for the input, and for a single file (`options.multiple: true` is refused). A form
type whose parent is `FileType` (through `getParent()`) is not detected as a file field.

### Free context pairs

With `allow_extra_context: true`, the free key/value pairs are displayed after the declared fields, under a "Context
(key/value)" label (no label when no context key is declared). With `allow_extra_context: false`, no free pair can be
added.

The key of a free pair follows the rule of the declared context keys: letters, digits, `_`, `-` and `:`, starting with
a letter, a digit or `_`, and not only digits. Otherwise, it is reported as a validation error on its key (`The
context key "a b" is not valid: ...`), and the process is not launched. The same applies to a free pair whose key is:
- a declared field (`The context key "delimiter" is a field of the launch form, use this field instead.`);
- `execution_user` (`The context key "execution_user" is reserved: it is set to the email of the connected user.`).

The key format rule also applies to the context pairs of the [schedules](05-scheduler.md).

> **Free pairs and placeholders.** A free pair gives a value to any context key, so to any `{{ key }}` placeholder of
> the options of the process tasks, not only to the keys declared in `form.context`. As soon as a process declares its
> context fields in `form.context`, set `allow_extra_context: false` so that only these keys can be set from the UI
> (the default stays `true`, for backward compatibility).

The HTML names of the fields are `launch[input]`, `launch[context][<key>]` for the declared fields and
`launch[context][extra][<index>][key|value]` for the free pairs.

### Context values

The values of the form are converted before being given to the process (after the validation, so that the
constraints apply to the typed values):

| Form data                                     | Process value                                                     |
|-----------------------------------------------|-------------------------------------------------------------------|
| `null`, `bool`, `int`, `float`, `string`      | unchanged                                                         |
| `array`                                       | converted recursively, keys kept                                  |
| backed enum                                   | its `value`                                                       |
| pure enum                                     | its `name`                                                        |
| `DateTimeInterface`                           | formatted as `DATE_ATOM` (`2026-01-02T03:04:05+01:00`)            |
| `DateInterval`                                | an ISO 8601 duration with every part (`P0Y0M1DT0H0M0S`), `-` prefixed when inverted |
| file                                          | refused (`LogicException`): file fields are only supported for the input |
| `Stringable` object                           | its string                                                        |
| other object                                  | refused (`LogicException`, 500)                                   |

A declared field left empty gives the `null` value (`false` for a `checkbox`).

> **`entity` fields.** The data of an `EntityType` is the entity itself: the `choice_value` option only changes the
> HTML value of the options, not the data given to the process. The entity must implement `\Stringable`: its string
> becomes the context value. Otherwise the launch fails with a `LogicException` ("cannot be used as a process context
> value"). Prefer a `choice` field with scalar choices (e.g. the identifiers of the entities).

Default values
--------------

*Deprecated since 3.3, use `form.input.default` and `form.context.<key>.default` (see
[migrating from the deprecated options](#migrating-from-the-deprecated-options)).*

`default.input` pre-fills the input field. It is ignored when `entrypoint_type` is `file` (a file field cannot be
pre-filled).

`default.context` is a list of `key`/`value` pairs (both required) pre-filling the context collection. The user can
change, remove or add pairs before launching. A pair whose key is declared in `form.context` pre-fills this field (if
the field has no `default`); when a key appears several times, the last pair wins.

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

*The `constraints` option is deprecated since 3.3, use `form.constraints`, which has the same syntax.*

`form.constraints` (or the deprecated `constraints`) uses the same syntax as the [ValidatorTask](https://github.com/cleverage/process-bundle/blob/main/docs/reference/tasks/validator_task.md)
of the process bundle (and as Symfony YAML validation mapping): a list of constraint short names (or FQCN) with their
options. Constraints are applied to the whole form data, which is an array with two keys:
- `input`: the data of the input field (e.g. a string, or the `UploadedFile` object of a `file` field),
- `context`: the context as an associative array (`key => value`), the declared keys first, with their typed values.

A violation on a declared context key (`[context][<key>]`) is displayed under its field.

While a constraint is violated, the process is not launched and the form is displayed again with the violation
messages.

```yaml
options:
    ui:
        ui_launch_mode: form
        form:
            input:
                type: file
            context:
                delimiter:
                    default: ','
            constraints:
                - Collection:
                      fields:
                          input:
                              - File:
                                    # A CSV file is often detected as "text/plain" (semicolon delimiter, few lines): allow it explicitly
                                    extensions:
                                        csv: ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'text/x-comma-separated-values']
                          context:
                              - Collection:
                                    fields:
                                        delimiter:
                                            - Choice:
                                                  choices: [',', ';']
                                                  message: 'delimiter context must be , or ;. {{ value }} given.'
```

The constraints of a single field are simpler to declare in its `options.constraints` (see
[form fields](#constraints-of-a-field)).

Note that the `Collection` constraint rejects missing and extra fields by default: use `allowExtraFields: true` (or
`allowMissingFields: true`) if users may add other context values.

See the [form with file upload](../cookbooks/form_file_upload.md) cookbook for a complete example.

Migrating from the deprecated options
-------------------------------------

Since 3.3, `entrypoint_type`, `default` and `constraints` are deprecated (a deprecation is triggered each time the
UI options of the process are resolved: process list, launch form, executions list; when the option is set, even to
an empty value), and will be removed in 4.0:

| Deprecated option   | Replacement                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `entrypoint_type`   | `form.input.type`                                                                             |
| `default.input`     | `form.input.default`                                                                          |
| `default.context`   | `form.context.<key>.default` (or a free pair with `form.allow_extra_context`)                 |
| `constraints`       | `form.constraints`                                                                            |

Until then, the deprecated options are folded into `form`. Mixing a deprecated option and its replacement raises an
`InvalidOptionsException`:
- `entrypoint_type` with `form.input`;
- `constraints` with `form.constraints` (even empty);
- `default.input` with `form.input.default`;
- a `default.context` pair with the `default` of the same declared key;
- a `default.context` pair whose key is not declared, with `form.allow_extra_context: false`.

See the [upgrade guide](../../UPGRADE.md) for an example.
