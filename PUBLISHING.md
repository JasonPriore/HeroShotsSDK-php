# Publishing

This package is published through Packagist. Composer/Packagist versions are
read from Git tags, not from a hardcoded `version` field in `composer.json`.

## One-time Packagist setup

1. Create or log in to a Packagist account.
2. Submit the GitHub repository:

   ```text
   https://github.com/JasonPriore/HeroShotsSDK-php
   ```

3. Packagist should detect the Composer package name:

   ```text
   heroshots/sdk
   ```

4. Enable auto-updates with the Packagist GitHub hook. The easiest way is to
   log in to Packagist with GitHub and let Packagist configure the hook.

If you do not use the GitHub hook, Packagist can still be updated manually from
the package page, or through the Packagist API.

## Release process

Run checks:

```bash
composer validate --strict
composer install --prefer-dist --no-progress
composer test
```

Create and push a new SemVer tag:

```bash
git tag v0.2.2
git push origin main
git push origin v0.2.2
```

Create a GitHub Release from that tag. Packagist will publish the new Composer
version from the tag.

Stable Packagist versions are immutable. If a release goes out wrong, publish a
new patch version instead of deleting and reusing the old tag.

## Local release helper

The ignored local script `./send_realese` automates checks, tagging, GitHub
Release creation, and optional Packagist refresh.

```bash
./send_realese
./send_realese minor Add new resource helpers
./send_realese 0.3.0 Release 0.3.0
```

To ask Packagist to refresh from the script, export:

```bash
export PACKAGIST_USERNAME="your-packagist-username"
export PACKAGIST_TOKEN="your-packagist-api-token"
```
