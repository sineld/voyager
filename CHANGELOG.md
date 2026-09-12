# Changelog

All notable changes to this project will be documented in this file.

## [2.0.0] - 2026-09-13

First release of `sineld/voyager`, forked from `kupidonkhv/voyager-fork` v1.7.43.

### Added
- Laravel 13 support; constraints opened to Laravel 14 and PHP 8.6 ahead of their release.
- `SchemaManager::createTable()`, which was called by the Database controller but never defined.
- `DatabaseUpdater::updateTable()` reimplemented on Laravel's native schema builder —
  add/drop/rename/change columns, add/drop indexes, rename tables.
- `SchemaBuilder`, translating Voyager table objects into Blueprint calls.
- `ImageFactory`, building Intervention's `ImageManager` from `config/image.php` without
  going through the shared `image` container binding.
- `DateFormatter`, accepting both strftime and PHP `date()` BREAD formats.
- `TableDoesNotExistException`, replacing Doctrine's `SchemaException`.
- CI matrix over PHP 8.4/8.5 × Laravel 12/13.

### Fixed
- PostgreSQL fatal: 47 type classes imported `Doctrine\DBAL\Platforms\AbstractPlatform`,
  which is not installed.
- MySQL-only `information_schema` queries in BREAD and Database listings replaced with
  `Schema::getTableListing()`; SQLite and PostgreSQL work again.
- Media manager uploads: migrated from the Intervention Image v2 API and the removed
  `Intervention\Image\Facades\Image` facade to v3.
- Laravel 13 claims the `image` container binding for its own image abstraction, which
  could hand Voyager the wrong manager.
- BREAD image handlers hardcoded the GD driver and ignored an Imagick configuration.
- `Carbon::formatLocalized()` removal in Carbon 3 caused a 500 on every BREAD with a
  formatted date/timestamp column.
- Column defaults gained a pair of quotes on every save.
- Laravel's schema keys (`nullable`, `auto_increment`, `primary`, `unique`) were not mapped
  to Voyager's (`notnull`, `autoincrement`, `is_primary`, `is_unique`).
- `Column::getNotnull()` returned the inverse of the truth.
- `Table::setPrimaryKey()` recorded a name but never registered an index.
- `findVersion()` hardcoded `tcg/voyager`, so forks reported an empty version.
- PHP 8.4 implicit-nullable deprecations in `Column::make()` and `Translatable::getTranslationsOf()`.
- Debug `file_put_contents()` to `storage/logs/voyager_debug.log` left in `Column::make()`.
- Test suite modernised for PHPUnit 13 (removed `getMockForAbstractClass()`,
  `returnValue()`, `addMethods()`, doc-comment metadata) — 135 tests, 902 assertions, green.


## [1.7.7] - 2025-09-13

### 🚀 Laravel 12 Compatibility

**Major Changes:**
- ✅ **Complete Doctrine DBAL removal** - No external Doctrine dependency
- ✅ **Native Laravel schema inspection** implemented for all database drivers
- ✅ **BasicTypes system** with 14 native Laravel type mappings

**New Type System:**
- BooleanType - Boolean values
- IntegerType - Integer numbers  
- FloatType - Floating point numbers
- DecimalType - Decimal numbers
- StringType - String values
- TextType - Text content
- CharType - Fixed-length characters
- VarCharType - Variable-length characters
- DateType - Date values
- DateTimeType - DateTime values
- TimeType - Time values
- JsonType - JSON data
- NumericType - Numeric values
- DoubleType - Double precision numbers

**Technical Improvements:**
- Updated DatabaseUpdater to use Laravel schema methods
- Enhanced Table class with proper diff functionality
- Fixed Type registration to exclude BasicTypes from platform types
- Improved null safety in blade templates

**Testing & Validation:**
- ✅ SQLite database integration tested
- ✅ All BREAD operations (CRUD) working
- ✅ Field validations and type mappings verified
- ✅ Rich text editor functionality confirmed
- ✅ Bulk delete operations tested

## [1.7.6] - 2025-09-13

### 🐛 Bug Fixes
- Fixed Doctrine replacement with Laravel Schema for Laravel 12 compatibility in BREAD panel

## [1.7.5] - 2025-09-13

### 🐛 Bug Fixes  
- Replaced deprecated getDoctrineSchemaManager with createSchemaManager for Laravel 12 compatibility

## [1.7.4] - 2025-09-13

### 📦 Dependencies
- Updated package version

## [1.7.3] - 2025-09-13

### 📦 Dependencies
- Updated package version

## [1.7.2] - 2025-09-13

### 📖 Documentation
- Updated README with Laravel 12 compatibility information

## [1.7.1] - 2025-09-13

### 🚀 Initial Fork
- Forked from thedevdojo/voyager
- Initial setup for Laravel 12 compatibility
- Updated dependencies for modern PHP versions
