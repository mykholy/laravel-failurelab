# Contributing

Thanks for helping improve reproducible failure testing for Laravel.

Please include: the exact original failure scenario, your expected business invariant, supported PHP/Laravel versions, a reproducible failing test, a focused fix, and an explanation of what the test proves **and does not prove**.

Development:

```bash
composer install
composer test
vendor/bin/pint --test
```

Avoid committing production credentials, real payment data, network-based fixtures, broad framework monkey patches, or code that targets real systems. Report sensitive vulnerabilities privately.

Breaking API changes require explicit discussion; stable releases require human review.


## Before opening a PR

1. Write a failing regression test that identifies the exact checkpoint,
   persisted effect, expected delivery count and negative control.
2. Run `composer test`, `composer analyse`, and `vendor/bin/pint --test`.
3. Do not broaden safety claims from an in-process test to a real-process or
   network guarantee. Point to the appropriate real database/subprocess CI.
4. Update [the docs index](docs/README.md), [changelog](CHANGELOG.md), and
   [safety guide](docs/SAFETY.md) when adding an externally visible API.
5. Maintain backward compatibility within the released SemVer major.
