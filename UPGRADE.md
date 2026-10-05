Upgrade Guide
=============

## v4.0

### CSRF protection of the login form

The `form_login` configuration prepended by the bundle now enables `enable_csrf` (token id `authenticate`, parameter
`_csrf_token`), and the login template renders the `_csrf_token` field. The Symfony CSRF protection must be enabled
(`framework.csrf_protection`, enabled by default when the session is).

If you override `@CleverAgeUiProcess/admin/login.html.twig` or submit the login form yourself, add the token:

```twig
<input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
```

To keep the previous behaviour, disable it on your `main` firewall:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            form_login:
                enable_csrf: false
```

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
