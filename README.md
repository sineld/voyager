<p align="center"><a href="https://voyager.devdojo.com" target="_blank"><img width="400" src="https://s3.amazonaws.com/thecontrolgroup/voyager.png"></a></p>

<p align="center">
  <a href="https://github.com/sineld/voyager/actions/workflows/tests.yml"><img src="https://github.com/sineld/voyager/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/sineld/voyager"><img src="https://img.shields.io/packagist/v/sineld/voyager" alt="Latest version"></a>
  <a href="https://packagist.org/packages/sineld/voyager"><img src="https://img.shields.io/packagist/dt/sineld/voyager" alt="Downloads"></a>
  <a href="license"><img src="https://img.shields.io/packagist/l/sineld/voyager" alt="License"></a>
</p>

# Voyager — The Missing Laravel Admin

A maintained fork of [Voyager](https://github.com/thedevdojo/voyager), the BREAD/CRUD admin
package for Laravel: media manager, menu builder, settings, roles and permissions, and a
database/table editor.

Upstream `tcg/voyager` stopped at Laravel 11 and PHP 8.3. This fork carries it forward to
**Laravel 12 / 13 / 14** on **PHP 8.4 / 8.5 / 8.6**, and repairs the features that broke
when Laravel dropped `doctrine/dbal` and Intervention Image moved to v3.

## Requirements

| | Supported |
|---|---|
| PHP | 8.4, 8.5, 8.6 |
| Laravel | 12.x, 13.x, 14.x |
| Databases | MySQL, MariaDB, PostgreSQL, SQLite, SQL Server |

## Installation

```bash
composer require sineld/voyager
```

Then install Voyager. Use `--with-dummy` to also get demo data (pages, posts, users, menus):

```bash
php artisan voyager:install

# or, with demo content
php artisan voyager:install --with-dummy
```

Create an admin user:

```bash
php artisan voyager:admin your@email.com --create
```

The panel lives at `/admin` by default. Change it with `voyager.user.redirect` and the
route prefix in `config/voyager.php`.

### Upgrading from `tcg/voyager` or `kupidonkhv/voyager-fork`

The namespace is unchanged (`TCG\Voyager\…`), so it is a drop-in replacement:

```bash
composer remove tcg/voyager        # or kupidonkhv/voyager-fork
composer require sineld/voyager
```

Nothing in your BREADs, migrations, published views or models needs to change. If you
published Voyager's views from an older release, delete
`resources/views/vendor/voyager` so the current views are used.

## What this fork fixes

Everything below was broken in `kupidonkhv/voyager-fork` v1.7.43 and is fixed here.

**The table editor works again (Tools → Database).** `SchemaManager::createTable()` was
called but never defined, and `DatabaseUpdater::updateTable()` threw
`RuntimeException('temporarily disabled')`. Creating, altering, renaming and dropping
tables is reimplemented on Laravel's native schema builder — no `doctrine/dbal`.

**PostgreSQL no longer fatals.** 47 type classes still imported
`Doctrine\DBAL\Platforms\AbstractPlatform`, a class that is not installed, so loading the
Postgres type set was a hard fatal error.

**Every driver works again.** BREAD and database table listing ran a hardcoded
`information_schema` query, which is MySQL-only. Replaced with Laravel's
`Schema::getTableListing()`.

**Media manager uploads work.** The media controller still used the Intervention Image v2
API (`encode($ext, $quality)`, `fit()`, `insert()`, and the `Intervention\Image\Facades\Image`
facade) against a v3 dependency.

**Laravel 13 image binding conflict resolved.** Laravel 13 ships its own image abstraction
and claims the same `image` container binding that Intervention's facade uses, so Voyager
could be handed the wrong manager. Voyager now builds its own `ImageManager` and no longer
touches that binding — while still honouring the driver in `config/image.php`. Previously
the BREAD image handlers hardcoded GD and silently ignored an Imagick configuration.

**Date columns render again.** Carbon 3 removed `formatLocalized()`, which the BREAD browse
and read views called for every `date`/`timestamp` column — a 500 on any BREAD with a
formatted date. Both strftime-style formats (`%B %e, %Y`) from older BREADs and plain PHP
`date()` formats are accepted.

**Column defaults stopped growing quotes.** Drivers report defaults as SQL literals
(`'hello'`), which Voyager stored verbatim, so every save wrapped the value in another pair
of quotes.

**Schema reads are correct.** Laravel's schema reader returns `nullable`, `auto_increment`,
`primary` and `unique`; Voyager expected `notnull`, `autoincrement`, `is_primary` and
`is_unique`, so nullability, auto-increment and index types were misread. `getNotnull()`
also returned the inverse of the truth.

**The version number shows up.** `findVersion()` looked for the hardcoded package name
`tcg/voyager`, so any fork reported an empty version in the admin footer. It now reads the
package's own name from its `composer.json`.

**PHP 8.4 deprecations** (implicit nullable parameters) and a stray debug `file_put_contents`
to `storage/logs/voyager_debug.log` are gone.

## Testing

```bash
composer install
vendor/bin/phpunit
```

135 tests run green on PHP 8.4 and 8.5 against Laravel 12 and 13. CI covers that matrix on
every push and weekly.

PHPUnit marks every test "risky" because `laravel/browser-kit-testing` does not restore its
error handlers. That is upstream and does not affect results.

## Laravel 14 and PHP 8.6

Neither has been released yet, so the constraints (`illuminate/support: ^12.0|^13.0|^14.0`,
`php: ^8.4`) are deliberately forward-looking and **untested** against them. When they ship,
add them to the CI matrix in `.github/workflows/tests.yml` and cut a release.

## Documentation

The upstream documentation still applies: <https://voyager-docs.devdojo.com/>

## Credits

- [Tony Lea](https://github.com/tnylea) and The Control Group — original author of Voyager
- [kupidonkhv](https://github.com/kupidonkhv/voyager) — the Laravel 12 fork this one is based on
- [Sinan Eldem](https://www.sinaneldem.com.tr) — current maintainer

## License

Voyager is open-sourced software licensed under the [MIT license](license).
