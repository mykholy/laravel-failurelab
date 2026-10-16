# GitHub repository metadata — Laravel FailureLab

GitHub repository Settings → General → **Description**, and the repository homepage **About** panel can be updated by an owner/maintainer. These are repository settings and are not populated automatically from `composer.json`.

**Description** (copy exactly):

> Deterministic Laravel fault injection and replay testing. Prove idempotency, webhook recovery and lost acknowledgements with PHPUnit.

**Website:** https://packagist.org/packages/mykholy/laravel-failurelab

**Topics:** `laravel`, `php`, `phpunit`, `testing`, `fault-injection`, `failure-testing`, `webhooks`, `idempotency`, `resilience`, `integration-testing`, `replay`, `laravel-package`

Do not claim that FailureLab injects real process crashes, performs distributed worker concurrency testing or guarantees exactly-once external effects. Those are explicitly outside its 1.1 API scope.

The canonical installation command is:

```bash
composer require --dev mykholy/laravel-failurelab:^1.0
```
