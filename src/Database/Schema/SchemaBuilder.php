<?php

namespace TCG\Voyager\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as LaravelSchema;

/**
 * Translates Voyager's Table/Column/Index/ForeignKey objects into native Laravel
 * schema operations.
 *
 * Laravel 11 dropped doctrine/dbal and gained native column change/rename support,
 * so everything here goes through the framework's own Blueprint. That keeps the
 * package working on every driver Laravel supports instead of MySQL only.
 */
class SchemaBuilder
{
    /**
     * Voyager / SQL type name => Blueprint method.
     */
    protected const TYPE_MAP = [
        'bigint' => 'bigInteger',
        'biginteger' => 'bigInteger',
        'int' => 'integer',
        'integer' => 'integer',
        'mediumint' => 'mediumInteger',
        'mediuminteger' => 'mediumInteger',
        'smallint' => 'smallInteger',
        'smallinteger' => 'smallInteger',
        'tinyint' => 'tinyInteger',
        'tinyinteger' => 'tinyInteger',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'real' => 'double',
        'char' => 'char',
        'varchar' => 'string',
        'string' => 'string',
        'text' => 'text',
        'tinytext' => 'tinyText',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'blob' => 'binary',
        'binary' => 'binary',
        'varbinary' => 'binary',
        'date' => 'date',
        'datetime' => 'dateTime',
        'datetimetz' => 'dateTimeTz',
        'time' => 'time',
        'timetz' => 'timeTz',
        'timestamp' => 'timestamp',
        'timestamptz' => 'timestampTz',
        'year' => 'year',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'uuid' => 'uuid',
        'guid' => 'uuid',
        'inet' => 'ipAddress',
        'macaddr' => 'macAddress',
        'enum' => 'enum',
        'set' => 'set',
        'geometry' => 'geometry',
        'point' => 'geometry',
        'polygon' => 'geometry',
    ];

    /**
     * Integer types that can carry an auto-increment flag.
     */
    protected const AUTO_INCREMENT_MAP = [
        'bigInteger' => 'bigIncrements',
        'integer' => 'increments',
        'mediumInteger' => 'mediumIncrements',
        'smallInteger' => 'smallIncrements',
        'tinyInteger' => 'tinyIncrements',
    ];

    /**
     * Create a table from a Voyager Table object.
     */
    public static function createTable(Table $table): void
    {
        $name = $table->getName();

        if (LaravelSchema::hasTable($name)) {
            throw new \RuntimeException("table {$name} already exists");
        }

        LaravelSchema::create($name, function (Blueprint $blueprint) use ($table) {
            foreach ($table->getColumns() as $column) {
                static::addColumn($blueprint, $column);
            }

            foreach ($table->getIndexes() as $index) {
                static::addIndex($blueprint, $index, $table);
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                static::addForeignKey($blueprint, $foreignKey);
            }
        });
    }

    /**
     * Add a column definition to a blueprint.
     *
     * @param  bool  $change  emit ->change() so an existing column is modified
     */
    public static function addColumn(Blueprint $blueprint, Column $column, bool $change = false): void
    {
        $name = $column->getName();
        $method = static::blueprintMethod($column);
        $definition = null;

        // Auto-increment columns are their own Blueprint methods and always primary.
        if (! $change && $column->getAutoincrement() && isset(static::AUTO_INCREMENT_MAP[$method])) {
            $blueprint->{static::AUTO_INCREMENT_MAP[$method]}($name);

            return;
        }

        $definition = match ($method) {
            'string', 'char' => $blueprint->{$method}($name, $column->getLength() ?: 255),
            'decimal', 'float', 'double' => $blueprint->{$method}(
                $name,
                $column->getPrecision() ?: 8,
                $column->getScale() ?: 2
            ),
            'enum', 'set' => $blueprint->{$method}($name, static::allowedValues($column)),
            default => $blueprint->{$method}($name),
        };

        if ($column->getUnsigned() && method_exists($definition, 'unsigned')) {
            $definition->unsigned();
        }

        // Voyager stores "notnull"; a column is nullable when notnull is falsy.
        $definition->nullable(! static::isNotNull($column));

        $default = $column->getDefault();
        if ($default !== null && $default !== '') {
            $definition->default($default);
        }

        if ($comment = $column->getComment()) {
            $definition->comment($comment);
        }

        if ($change) {
            $definition->change();
        }
    }

    /**
     * Whether the column is NOT NULL.
     *
     * Column::getNotnull() inverts the stored flag for historical reasons, so read
     * the raw option here and keep the semantics obvious at the call site.
     */
    public static function isNotNull(Column $column): bool
    {
        $options = $column->getOptions();

        return (bool) ($options['notnull'] ?? false);
    }

    protected static function allowedValues(Column $column): array
    {
        $options = $column->getOptions();
        $values = $options['allowed'] ?? $options['values'] ?? [];

        if (is_string($values)) {
            $values = array_filter(array_map('trim', explode(',', $values)));
        }

        return empty($values) ? [''] : array_values($values);
    }

    /**
     * Resolve the Blueprint method for a column's type.
     */
    public static function blueprintMethod(Column $column): string
    {
        $type = $column->getType();
        $name = is_object($type) ? $type->getName() : (string) $type;
        $name = strtolower(trim($name));

        // Strip "varchar(255)" / "bigint(20) unsigned" style declarations.
        $name = preg_replace('/\s*unsigned\s*/', '', $name);
        $name = preg_replace('/\([^)]*\)/', '', $name);
        $name = trim($name);

        return static::TYPE_MAP[$name] ?? 'string';
    }

    public static function addIndex(Blueprint $blueprint, $index, ?Table $table = null): void
    {
        $columns = $index->getColumns();
        $name = $index->getName();

        if ($index->isPrimary()) {
            // increments() already declared the primary key.
            if ($table && static::hasAutoIncrementColumn($table, $columns)) {
                return;
            }

            $blueprint->primary($columns);

            return;
        }

        if ($index->isUnique()) {
            $blueprint->unique($columns, $name);

            return;
        }

        $blueprint->index($columns, $name);
    }

    protected static function hasAutoIncrementColumn(Table $table, array $columns): bool
    {
        foreach ($columns as $columnName) {
            if ($table->hasColumn($columnName) && $table->getColumn($columnName)->getAutoincrement()) {
                return true;
            }
        }

        return false;
    }

    public static function addForeignKey(Blueprint $blueprint, $foreignKey): void
    {
        $localColumns = (array) $foreignKey->getLocalColumns();
        $foreignColumns = (array) $foreignKey->getForeignColumns();

        $options = $foreignKey->getOptions();

        $definition = $blueprint->foreign($localColumns, $foreignKey->getName())
            ->references(count($foreignColumns) === 1 ? $foreignColumns[0] : $foreignColumns)
            ->on($foreignKey->getForeignTableName());

        if (! empty($options['onUpdate'])) {
            $definition->onUpdate($options['onUpdate']);
        }

        if (! empty($options['onDelete'])) {
            $definition->onDelete($options['onDelete']);
        }
    }
}
