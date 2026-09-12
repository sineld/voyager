<?php

namespace TCG\Voyager\Database\Schema;

use Illuminate\Support\Facades\Schema as LaravelSchema;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Types\Type;

class Table
{
    protected $name;
    protected $columns = [];
    protected $indexes = [];
    protected $foreignKeys = [];
    protected $options = [];
    protected $primaryKeyName;

    public function __construct($name, $columns = [], $indexes = [], $foreignKeys = [], $options = [])
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->indexes = $indexes;
        $this->foreignKeys = $foreignKeys;
        $this->options = $options;
    }

    public function addColumn($name, $type, $options = [])
    {
        // Convert string type to Type object
        if (is_string($type)) {
            $typeObj = Type::getType($type);
            if (!$typeObj) {
                throw new \RuntimeException("Type {$type} not found");
            }
            $type = $typeObj;
        }
        
        $column = new Column($name, $type, $options);
        $this->columns[$name] = $column;
        return $this;
    }

    public function setPrimaryKey($columns, $name = 'primary')
    {
        $this->primaryKeyName = $name;

        // Register a real index too, otherwise toArray() loses the primary key.
        return $this->addPrimaryKey($columns, $name);
    }

    public static function make($table)
    {
        if (!is_array($table)) {
            $table = json_decode($table, true);
        }

        $name = Identifier::validate($table['name'], 'Table');

        $columns = [];
        foreach ($table['columns'] as $columnArr) {
            $column = Column::make($columnArr, $table['name']);
            $columns[$column->getName()] = $column;
        }

        $indexes = [];
        foreach ($table['indexes'] as $indexArr) {
            $index = Index::make($indexArr);
            $indexes[$index->getName()] = $index;
        }

        $foreignKeys = [];
        foreach ($table['foreignKeys'] as $foreignKeyArr) {
            $foreignKey = ForeignKey::make($foreignKeyArr);
            $foreignKeys[$foreignKey->getName()] = $foreignKey;
        }

        $options = $table['options'];

        return new self($name, $columns, $indexes, $foreignKeys, $options);
    }

    public function hasColumn($name)
    {
        return isset($this->columns[$name]);
    }

    public function getColumn($name)
    {
        if (!isset($this->columns[$name])) {
            throw new \RuntimeException("Column {$name} does not exist on table {$this->name}");
        }

        return $this->columns[$name];
    }

    public function removeColumn($name)
    {
        unset($this->columns[$name]);

        return $this;
    }

    public function hasIndex($name)
    {
        return isset($this->indexes[$name]);
    }

    public function getIndex($name)
    {
        if (!isset($this->indexes[$name])) {
            throw new \RuntimeException("Index {$name} does not exist on table {$this->name}");
        }

        return $this->indexes[$name];
    }

    public function addIndex($columns, $name = null, $type = Index::INDEX)
    {
        $columns = (array) $columns;
        $name = $name ?: Index::createName($columns, strtolower($type), $this->name);

        $this->indexes[$name] = Index::make([
            'name'    => $name,
            'columns' => $columns,
            'type'    => $type,
        ]);

        return $this;
    }

    public function addUniqueIndex($columns, $name = null)
    {
        return $this->addIndex($columns, $name, Index::UNIQUE);
    }

    public function addPrimaryKey($columns, $name = 'primary')
    {
        return $this->addIndex($columns, $name, Index::PRIMARY);
    }

    public function getPrimaryKey()
    {
        foreach ($this->indexes as $index) {
            if ($index->isPrimary()) {
                return $index;
            }
        }

        return null;
    }

    public function getPrimaryKeyColumns()
    {
        $primary = $this->getPrimaryKey();

        return $primary ? $primary->getColumns() : [];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getColumnsIndexes($columns, $sort = false)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $matched = [];

        foreach ($this->indexes as $index) {
            if ($this->spansColumns($index, $columns)) {
                $matched[$index->getName()] = $index;
            }
        }

        if (count($matched) > 1 && $sort) {
            // Sort indexes based on priority: PRI > UNI > IND
            uasort($matched, function ($index1, $index2) {
                $index1_type = Index::getType($index1);
                $index2_type = Index::getType($index2);

                if ($index1_type == $index2_type) {
                    return 0;
                }

                if ($index1_type == Index::PRIMARY) {
                    return -1;
                }

                if ($index2_type == Index::PRIMARY) {
                    return 1;
                }

                if ($index1_type == Index::UNIQUE) {
                    return -1;
                }

                // If we reach here, it means: $index1=INDEX && $index2=UNIQUE
                return 1;
            });
        }

        return $matched;
    }

    protected function spansColumns($index, $columns)
    {
        $indexColumns = $index->getColumns();
        return count(array_intersect($indexColumns, $columns)) == count($columns);
    }

    public function diff($compareTable)
    {
        // For Laravel 12 compatibility, diff functionality needs to be reimplemented
        // This is a complex operation that requires comparing schema structures
        throw new \RuntimeException('Table diff functionality is not yet implemented for Laravel 12');
    }

    public function diffOriginal()
    {
        // For Laravel 12 compatibility, diff functionality needs to be reimplemented
        throw new \RuntimeException('Table diff functionality is not yet implemented for Laravel 12');
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'name'           => $this->name,
            'oldName'        => $this->name,
            'columns'        => $this->exportColumnsToArray(),
            'indexes'        => $this->exportIndexesToArray(),
            'primaryKeyName' => $this->primaryKeyName,
            'foreignKeys'    => $this->exportForeignKeysToArray(),
            'options'        => $this->options,
        ];
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * @return array
     */
    public function exportColumnsToArray()
    {
        $exportedColumns = [];

        foreach ($this->columns as $name => $column) {
            $exportedColumns[] = Column::toArray($column);
        }

        return $exportedColumns;
    }

    /**
     * @return array
     */
    public function exportIndexesToArray()
    {
        $exportedIndexes = [];

        foreach ($this->indexes as $name => $index) {
            $indexArr = Index::toArray($index);
            $indexArr['table'] = $this->name;
            $exportedIndexes[] = $indexArr;
        }

        return $exportedIndexes;
    }

    /**
     * @return array
     */
    public function exportForeignKeysToArray()
    {
        $exportedForeignKeys = [];

        foreach ($this->foreignKeys as $name => $fk) {
            $exportedForeignKeys[$name] = ForeignKey::toArray($fk);
        }

        return $exportedForeignKeys;
    }

    public function __get($property)
    {
        $getter = 'get'.ucfirst($property);

        if (!method_exists($this, $getter)) {
            throw new \Exception("Property {$property} doesn't exist or is unavailable");
        }

        return $this->$getter();
    }
}
