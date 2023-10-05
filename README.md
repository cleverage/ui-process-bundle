![Code Style](https://github.com/cleverage/processuibundle/actions/workflows/super-linter.yml/badge.svg) ![Composer](https://github.com/cleverage/processuibundle/actions/workflows/php.yml/badge.svg)

## CleverAge/ProcessUIBundle
A simple UX for cleverage/processbundle using EasyAdmin

**Installation**
* Import routes
```yaml
#config/routes.yaml
processui:
    resource: '@CleverAgeProcessUiBundle/src/Controller'
    type: attribute
```
* Run doctrine migration
* Create an user using cleverage:process-ui:user-create console.

Now you can access Process UI via http://your-domain.com/process
