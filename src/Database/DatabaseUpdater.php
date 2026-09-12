<?php

namespace TCG\Voyager\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as LaravelSchema;
use TCG\Voyager\Database\Schema\SchemaBuilder;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Database\Schema\Table;
use TCG\Voyager\Database\Types\Type;
use TCG\Voyager\Exceptions\TableDoesNotExistException;

/**
 * Applies the difference between the table as it exists in the database and the
 * table as the Database/BREAD editor submitted it.
 *
 * Laravel 11+ renames and changes columns natively, so there is no doctrine/dbal here.
 */
class DatabaseUpdater
{
    protected $tableArr;
    protected $table;
    protected $originalTable;

    public function __construct(array $tableArr)
    {
        Type::registerCustomPlatformTypes();

        $this->table = Table::make($tableArr);
        $this->tableArr = $tableArr;
        $this->originalTable = SchemaManager::listTableDetails($tableArr['oldName']);
    }

    /**
     * Update the table.
     *
     * @return void
     */
    public static function update($table)
    {
        if (!is_array($table)) {
            $table = json_decode($table, true);
        }

        if (!SchemaManager::tableExists($table['oldName'])) {
            throw new TableDoesNotExistException($table['oldName']);
        }

        (new self($table))->updateTable();
    }

    /**
     * Apply every pending change to the table.
     *
     * @return void
     */
    public function updateTable()
    {
        $tableName = $this->originalTable->getName();

        $this->renameColumns($tableName);
        $this->dropRemovedColumns($tableName);
        $this->addNewColumns($tableName);
        $this->changeExistingColumns($tableName);
        $this->syncIndexes($tableName);

        $this->renameTable($tableName);
    }

    /**
     * Columns whose `oldName` no longer matches `name`.
     */
    protected function renameColumns($tableName)
    {
        foreach ($this->getRenamedColumns() as $from => $to) {
            LaravelSchema::table($tableName, function (Blueprint $blueprint) use ($from, $to) {
                $blueprint->renameColumn($from, $to);
            });
        }
    }

    protected function dropRemovedColumns($tableName)
    {
        $keep = array_keys($this->table->getColumns());
        $renamed = $this->getRenamedColumns();

        $drop = [];
        foreach (array_keys($this->originalTable->getColumns()) as $existing) {
            // A renamed column still exists, just under its new name.
            if (isset($renamed[$existing]) || in_array($existing, $keep, true)) {
                continue;
            }

            $drop[] = $existing;
        }

        if (empty($drop)) {
            return;
        }

        LaravelSchema::table($tableName, function (Blueprint $blueprint) use ($drop) {
            $blueprint->dropColumn($drop);
        });
    }

    protected function addNewColumns($tableName)
    {
        $existing = SchemaManager::listTableColumnNames($tableName);

        $new = array_filter(
            $this->table->getColumns(),
            fn ($column) => !in_array($column->getName(), $existing, true)
        );

        if (empty($new)) {
            return;
        }

        LaravelSchema::table($tableName, function (Blueprint $blueprint) use ($new) {
            foreach ($new as $column) {
                SchemaBuilder::addColumn($blueprint, $column);
            }
        });
    }

    /**
     * Re-emit surviving columns with ->change() when their definition moved.
     */
    protected function changeExistingColumns($tableName)
    {
        $original = $this->originalTable->getColumns();
        $renamed = array_flip($this->getRenamedColumns());

        $changed = [];
        foreach ($this->table->getColumns() as $column) {
            $originalName = $renamed[$column->getName()] ?? $column->getName();

            if (!isset($original[$originalName])) {
                continue; // freshly added above
            }

            if ($this->columnDiffers($original[$originalName], $column)) {
                $changed[] = $column;
            }
        }

        if (empty($changed)) {
            return;
        }

        LaravelSchema::table($tableName, function (Blueprint $blueprint) use ($changed) {
            foreach ($changed as $column) {
                SchemaBuilder::addColumn($blueprint, $column, change: true);
            }
        });
    }

    protected function columnDiffers($original, $updated): bool
    {
        if (SchemaBuilder::blueprintMethod($original) !== SchemaBuilder::blueprintMethod($updated)) {
            return true;
        }

        if (SchemaBuilder::isNotNull($original) !== SchemaBuilder::isNotNull($updated)) {
            return true;
        }

        if ((string) $original->getDefault() !== (string) $updated->getDefault()) {
            return true;
        }

        return (int) $original->getLength() !== (int) $updated->getLength();
    }

    protected function syncIndexes($tableName)
    {
        $original = $this->originalTable->getIndexes();
        $updated = $this->table->getIndexes();

        $added = array_diff_key($updated, $original);
        $removed = array_diff_key($original, $updated);

        if (empty($added) && empty($removed)) {
            return;
        }

        $table = $this->table;

        LaravelSchema::table($tableName, function (Blueprint $blueprint) use ($added, $removed, $table) {
            foreach ($removed as $index) {
                // Dropping a primary key is destructive and unsupported on SQLite,
                // and the editor has no affordance for it. Leave it alone.
                if ($index->isPrimary()) {
                    continue;
                }

                if ($index->isUnique()) {
                    $blueprint->dropUnique($index->getName());
                } else {
                    $blueprint->dropIndex($index->getName());
                }
            }

            foreach ($added as $index) {
                SchemaBuilder::addIndex($blueprint, $index, $table);
            }
        });
    }

    protected function renameTable($tableName)
    {
        $newName = $this->table->getName();

        if ($newName !== $tableName) {
            LaravelSchema::rename($tableName, $newName);
        }
    }

    /**
     * Get columns that were renamed, as [oldName => newName].
     *
     * @return array
     */
    protected function getRenamedColumns()
    {
        $renamed = [];

        foreach ($this->tableArr['columns'] ?? [] as $column) {
            $old = $column['oldName'] ?? null;
            $new = $column['name'] ?? null;

            if ($old && $new && $old !== $new && $this->originalTable->hasColumn($old)) {
                $renamed[$old] = $new;
            }
        }

        return $renamed;
    }

    /**
     * Get indexes that were renamed.
     *
     * @return array
     */
    protected function getRenamedIndexes()
    {
        return [];
    }
}
