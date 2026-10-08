Upgrade Guide
=============

## v4.0

### API token generation

The "generateToken" action of the users (edit page) is now a POST form with a CSRF token: the route
`/process/user/{id}/generate-token` only accepts POST requests with a valid `csrfToken`. Generate the API tokens from
the button of the user edit page; the direct GET URL returns a 405.

## v3.0

### Import routes

```yaml
// Before (2.x)
ui-process-bundle:
  resource: '@CleverAgeUiProcessBundle/src/Controller'
  type: attribute

// After (3.x)
ui-process-bundle:
  resource: '@CleverAgeUiProcessBundle/config/routes/*.yaml'
```
### EasyAdmin

If you have specific EasyAdmin code on your project, please follow the official [Upgrade Guide](https://github.com/EasyCorp/EasyAdminBundle/blob/5.x/UPGRADE.md)
