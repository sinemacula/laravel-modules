# Changelog

## [1.3.0](https://github.com/sinemacula/laravel-modules/compare/v1.2.0...v1.3.0) (2026-07-31)


### Features

* prune missing view paths and expose a public module state reset ([#59](https://github.com/sinemacula/laravel-modules/issues/59)) ([86b4ba5](https://github.com/sinemacula/laravel-modules/commit/86b4ba56293a318d76926143cd4b7d9233799fa1))

## [1.2.0](https://github.com/sinemacula/laravel-modules/compare/v1.1.0...v1.2.0) (2026-07-31)


### Features

* canonicalise the module base path and flush state when it changes ([#50](https://github.com/sinemacula/laravel-modules/issues/50)) ([0a7e853](https://github.com/sinemacula/laravel-modules/commit/0a7e853ae170421a555325c44cef4262f7d01f80))
* **commands:** scaffold the resource and schedule conventions ([#53](https://github.com/sinemacula/laravel-modules/issues/53)) ([a7f4e64](https://github.com/sinemacula/laravel-modules/commit/a7f4e6445bbb4663abef0f8aa89003c952646809))


### Bug Fixes

* discard the module cache once the module set has changed ([#47](https://github.com/sinemacula/laravel-modules/issues/47)) ([8fdb8b6](https://github.com/sinemacula/laravel-modules/commit/8fdb8b6cbfbbfb2c996d98ebaa58f35a8523773e))
* order discovered modules and reject manifests with unusable paths ([#51](https://github.com/sinemacula/laravel-modules/issues/51)) ([363176c](https://github.com/sinemacula/laravel-modules/commit/363176cd18d45570ead3846e90df4218d31b0e7d))
* reject module directories that cannot be resolved ([#52](https://github.com/sinemacula/laravel-modules/issues/52)) ([9bd0635](https://github.com/sinemacula/laravel-modules/commit/9bd0635dc9a702f7af0227a29a96f2ee6f624fc2))

## [1.1.0](https://github.com/sinemacula/laravel-modules/compare/v1.0.2...v1.1.0) (2026-06-27)


### Features

* support Laravel 12 alongside Laravel 13 ([#15](https://github.com/sinemacula/laravel-modules/issues/15)) ([a7a6a52](https://github.com/sinemacula/laravel-modules/commit/a7a6a52fd48929dc25d9d398c88e1158a39c4369))

## [1.0.2](https://github.com/sinemacula/laravel-modules/compare/v1.0.1...v1.0.2) (2026-06-25)


### Bug Fixes

* harden CLI command failure reporting and module discovery ([#13](https://github.com/sinemacula/laravel-modules/issues/13)) ([641263a](https://github.com/sinemacula/laravel-modules/commit/641263a0ce08d745b9d29a1e80c7ac19c6d31ead))
* harden module discovery and the cache command (release prep) ([#10](https://github.com/sinemacula/laravel-modules/issues/10)) ([f8044c6](https://github.com/sinemacula/laravel-modules/commit/f8044c62696b74fbc859ebe0e0cbcfe442b89f38))
