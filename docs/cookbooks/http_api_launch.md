Launch a process through the HTTP API
=====================================

This recipe lets another application (an ERP, a CI job, a webhook...) trigger a process, with parameters, through an
HTTP request. Here, the calling application asks to fetch the communes of a postal code from a REST API.

```yaml
# config/packages/process/app.fetch_communes.yaml
clever_age_process:
    configurations:
        app.fetch_communes:
            description: 'Fetch the communes of a postal code'
            help: 'Ex: bin/console cleverage:process:execute app.fetch_communes -c codePostal:"46800"'
            public: true # Default value: private processes cannot be launched through the HTTP API
            options:
                ui:
                    source: API Carto IGN
                    target: Logs
            tasks:
                request:
                    service: '@CleverAge\RestProcessBundle\Task\RequestTask'
                    options:
                        client: apicarto_ign
                        url: '/codes-postaux/communes/{codePostal}'
                        method: 'GET'
                        url_parameters: { codePostal: '{{ codePostal }}' } # Contextualized option, set by the HTTP call
                    outputs: [decode]

                decode:
                    service: '@CleverAge\ProcessBundle\Task\TransformerTask'
                    options:
                        transformers:
                            json_decode: ~ # Generic transformer defined in the application
                    outputs: [log]

                log:
                    service: '@CleverAge\ProcessBundle\Task\Reporting\LoggerTask'
                    options:
                        level: info
                        message: 'Communes fetched'
                        context: ['input']
```

First, create a technical user for the calling application, e.g.
`bin/console cleverage:ui-process:user-create erp@example.com 'a-long-random-password'`. Then, in the UI, open
"Users > User List", edit this user and click on "Generate token". Copy the token displayed in the flash message: it
will never be displayed again.

The calling application can now queue the process:

```bash
curl --location 'https://your-domain.com/http/process/execute' \
--header 'Authorization: Bearer myBearerToken' \
--header 'Content-Type: application/json' \
--data '{
    "code": "app.fetch_communes",
    "context": {"codePostal": "46800"}
}'
```

Response:

```json
"Process has been added to queue. It will start as soon as possible."
```

How it works:
- The `Authorization: Bearer` header is checked against the hashed token of the users
  (see [authentication](../reference/06-http_api.md#authentication)). A missing or invalid token returns a `401`.
- `code` must be the code of an existing, public process, otherwise a `422` is returned with the violation message
  (see [parameters](../reference/06-http_api.md#parameters)).
- `context` values are injected into the contextualized options, like `-c codePostal:"46800"` on the console. With
  form data instead of JSON, send them as `--form 'context[codePostal]="46800"'`.
- By default, the process is queued to the `execute_process` transport and executed by a
  `messenger:consume execute_process` worker (see [messenger](../reference/08-messenger.md)): the HTTP response does
  not wait for the end of the process. Add `"queue": false` to execute it during the request: the response is then
  `200` if the process succeeded, or `500` with the exception message.
- The execution, its logs and its report are available in "Process > Executions", like any other execution
  (see [process executions & logs](../reference/04-process_executions_and_logs.md)).

The `apicarto_ign` client is a [RestProcessBundle](https://github.com/cleverage/rest-process-bundle) client registered
in the application (a `CleverAge\RestProcessBundle\Client\Client` service with `$uri: 'https://apicarto.ign.fr/api'`,
tagged `cleverage.rest.client`), and `json_decode` a generic transformer (see
[generic transformers](https://github.com/cleverage/process-bundle/blob/main/docs/reference/03-generic_transformers_definition.md)).
To send a file to the process instead, see the file upload example of the
[form with file upload](form_file_upload.md) cookbook.
