<?php

namespace TCG\Voyager\Database\Schema;

use Illuminate\Support\Facades\Schema as LaravelSchema;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Types\Type;

abstract class SchemaManager
{
    public static function __callStatic($method, $args)
    {
        // Redirect to Laravel's Schema facade for basic operations
        if (method_exists(LaravelSchema::class, $method)) {
            return LaravelSchema::$method(...$args);
        }
        
        throw new \BadMethodCallException("Method {$method} not found");
    }

    public static function tableExists($table)
    {
        return LaravelSchema::hasTable($table);
    }

    public static function listTables()
    {
        $tables = [];

        foreach (static::listTableNames() as $tableName) {
            $tables[$tableName] = static::listTableDetails($tableName);
        }

        return $tables;
    }

    public static function listTableDetails($tableName)
    {
        Type::registerCustomPlatformTypes();

        $columns = LaravelSchema::getColumns($tableName);
        $indexes = LaravelSchema::getIndexes($tableName);
        $foreignKeys = LaravelSchema::getForeignKeys($tableName);

        // Convert Laravel schema arrays to Voyager objects
        $columnObjects = [];
        foreach ($columns as $columnArr) {
            $columnObjects[$columnArr['name']] = Column::make($columnArr, $tableName);
        }
        
        $indexObjects = [];
        foreach ($indexes as $indexArr) {
            $indexObjects[$indexArr['name']] = Index::make($indexArr);
        }
        
        $foreignKeyObjects = [];
        foreach ($foreignKeys as $fkArr) {
            $foreignKeyObjects[$fkArr['name']] = ForeignKey::make($fkArr);
        }
        
        return new Table($tableName, $columnObjects, $indexObjects, $foreignKeyObjects, []);
    }

    public static function describeTable($tableName)
    {
        Type::registerCustomPlatformTypes();

        $table = static::listTableDetails($tableName);

        return collect($table->getColumns())->map(function ($column) use ($table) {
            $columnArr = Column::toArray($column);

            $columnArr['field'] = $columnArr['name'];
            $columnArr['type'] = $columnArr['type']['name'];

            // Set the indexes and key
            $columnArr['indexes'] = [];
            $columnArr['key'] = null;
            
            if ($indexes = $table->getColumnsIndexes($columnArr['name'], true)) {
                // Convert indexes to Array
                $columnArr['indexes'] = [];
                foreach ($indexes as $name => $index) {
                    $columnArr['indexes'][$name] = Index::toArray($index);
                }

                // If there are multiple indexes for the column
                // the Key will be one with highest priority
                if (!empty($columnArr['indexes'])) {
                    $indexType = array_values($columnArr['indexes'])[0]['type'];
                    $columnArr['key'] = substr($indexType, 0, 3);
                }
            }

            return $columnArr;
        });
    }

    public static function listTableColumnNames($tableName)
    {
        Type::registerCustomPlatformTypes();

        $columnNames = [];
        
        foreach (LaravelSchema::getColumns($tableName) as $column) {
            $columnNames[] = $column['name'];
        }

        return $columnNames;
    }

    /**
     * Table names of the current connection, driver agnostic.
     *
     * Laravel scopes getTableListing() to the connection's own database/schema,
     * so this works the same on MySQL, MariaDB, PostgreSQL, SQLite and SQL Server.
     */
    public static function listTableNames()
    {
        $tableNames = LaravelSchema::getTableListing(schemaQualified: false);

        sort($tableNames);

        return $tableNames;
    }

    /**
     * Create a table from a Voyager Table object (or its array form).
     */
    public static function createTable($table)
    {
        Type::registerCustomPlatformTypes();

        if (!$table instanceof Table) {
            $table = Table::make($table);
        }

        SchemaBuilder::createTable($table);

        return $table;
    }

    public static function dropTable($tableName)
    {
        LaravelSchema::dropIfExists($tableName);
    }

    public static function renameTable($from, $to)
    {
        LaravelSchema::rename($from, $to);
    }

    public static function getDatabasePlatformName()
    {
        $driver = DB::connection()->getDriverName();
        
        // Map Laravel driver names to platform names
        $platformMap = [
            'mysql' => 'mysql',
            'pgsql' => 'postgresql', 
            'sqlite' => 'sqlite',
            'sqlsrv' => 'mssql',
        ];
        
        return $platformMap[$driver] ?? $driver;
    }
}
