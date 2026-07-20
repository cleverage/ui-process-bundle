Upgrade Guide
=============

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
