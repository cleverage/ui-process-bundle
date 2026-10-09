HTTP API
========

A process can be launched from another application with an HTTP `POST` request on the `http_process_execute` route.

Endpoint
--------

* **Route**: `http_process_execute`
* **Path**: `/http/process/execute`
* **Method**: `POST`
* **Authentication**: `Authorization: Bearer <token>` header

Authentication
--------------

The token is generated from the UI, on the user edit page ("Generate token" action, see
[users & security](03-users_and_security.md#api-token)). It is displayed once, only its hash is stored.

| Response                                           | Reason                                               |
|----------------------------------------------------|------------------------------------------------------|
| `401` `{"message": "Missing auth token."}`         | No `Authorization` header.                           |
| `401` `{"message": "Invalid token."}`              | The token does not match any user.                   |

Parameters
----------

Parameters can be sent either as a JSON body, or as form data (`application/x-www-form-urlencoded` or
`multipart/form-data`).

| Parameter | Type                      | Required | Default | Description                                                                                                                                                           |
|-----------|---------------------------|:--------:|---------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `code`    | `string`                  |    **X** |         | Code of the process. It must exist and be public.                                                                                                                    |
| `input`   | `string` or file          |          | `null`  | Process input. With form data, it can be an uploaded file: the file is saved in the `upload_directory` directory (see [bundle configuration](01-bundle_configuration.md#container-parameters)) and its path is passed as input. |
| `context` | `object` or JSON `string` |          | `{}`    | Process context, as key/value pairs. A JSON string must decode to an object or an array (`422` otherwise). With form data, send one field per value: `context[key]=value`. |
| `queue`   | `bool`                    |          | `true`  | `true`: the process is queued to the `execute_process` transport (see [messenger](08-messenger.md)). `false`: the process is executed during the HTTP request.      |

`code`, `input`, `context` and `queue` can also be passed in the query string, with form data or without body (the form
data takes precedence).

Responses
---------

| Status | Body                                                                            | Case                                                                              |
|--------|---------------------------------------------------------------------------------|-----------------------------------------------------------------------------------|
| `200`  | `"Process has been added to queue. It will start as soon as possible."`       | `queue` is `true`.                                                                |
| `200`  | `"Process has been proceed well."`                                              | `queue` is `false` and the process succeeded.                                    |
| `500`  | The exception message and `(process execution: <id>)`, as a JSON string         | `queue` is `false` and the process failed.                                       |
| `422`  | Violation messages, e.g. `Process code is required.`, `The process "foo" does not exist.`, `The process "foo" is not public.`, `Context must be a JSON object or array.` | Invalid parameters. A request body that cannot be parsed is handled as an empty request. |

Once the process has started, its execution is recorded in the [executions list](04-process_executions_and_logs.md), like any
other execution.

Examples
--------

JSON body:

```bash
curl --location 'https://your-domain.com/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--header 'Content-Type: application/json' \
--data '{
    "code": "demo.die",
    "context": {"foo": "bar"}
}'
```

File upload, with context values:

```bash
curl --location 'https://your-domain.com/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--form 'code="demo.upload_and_run"' \
--form 'input=@/path/to/your/file.csv' \
--form 'context[delimiter]=";"'
```

Synchronous execution:

```bash
curl --location 'https://your-domain.com/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--form 'code="demo.dummy"' \
--form 'queue="false"'
```

Prefer queued executions for long processes: a synchronous execution is subject to the web server and PHP timeouts.

If you use Apache with PHP-FPM, make sure the `Authorization` header is passed to PHP (see
[troubleshooting](../troubleshooting.md)).

See the [HTTP API](../cookbooks/http_api_launch.md) cookbook for a complete example.
