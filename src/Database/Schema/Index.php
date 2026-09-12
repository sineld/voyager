<?php

namespace TCG\Voyager\Database\Schema;



abstract class Index
{
    public const PRIMARY = 'PRIMARY';
    public const UNIQUE = 'UNIQUE';
    public const INDEX = 'INDEX';

    public static function make(array $index)
    {
        $columns = $index['columns'];
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        if (isset($index['type'])) {
            $type = $index['type'];

            $isPrimary = ($type == static::PRIMARY);
            $isUnique = $isPrimary || ($type == static::UNIQUE);
        } else {
            // `primary`/`unique` come from Laravel's schema reader,
            // the snake/camel variants from Voyager's own editor payloads.
            $isPrimary = $index['primary'] ?? $index['is_primary'] ?? $index['isPrimary'] ?? false;
            $isUnique = $index['unique'] ?? $index['is_unique'] ?? $index['isUnique'] ?? $isPrimary;

            // Set the type
            if ($isPrimary) {
                $type = static::PRIMARY;
            } elseif ($isUnique) {
                $type = static::UNIQUE;
            } else {
                $type = static::INDEX;
            }
        }

        // Set the name
        $name = trim($index['name'] ?? '');
        if (empty($name)) {
            $table = $index['table'] ?? null;
            $name = static::createName($columns, $type, $table);
        } else {
            $name = Identifier::validate($name, 'Index');
        }

        $flags = $index['flags'] ?? [];
        $options = $index['options'] ?? [];

        return new IndexObject($name, $columns, $isUnique, $isPrimary, $flags, $options);
    }

    /**
     * @return array
     */
    public static function toArray($index)
    {
        if (is_array($index)) {
            $name = $index['name'] ?? '';
            $columns = $index['columns'] ?? [];

            return [
                'name'        => $name,
                'oldName'     => $name,
                'columns'     => $columns,
                'type'        => static::getType($index),
                'isPrimary'   => $index['is_primary'] ?? false,
                'isUnique'    => $index['is_unique'] ?? false,
                'isComposite' => count($columns) > 1,
                'flags'       => $index['flags'] ?? [],
                'options'     => $index['options'] ?? [],
            ];
        } else {
            // Handle IndexObject
            return [
                'name'        => $index->getName(),
                'oldName'     => $index->getName(),
                'columns'     => $index->getColumns(),
                'type'        => $index->isPrimary() ? static::PRIMARY : ($index->isUnique() ? static::UNIQUE : static::INDEX),
                'isPrimary'   => $index->isPrimary(),
                'isUnique'    => $index->isUnique(),
                'isComposite' => count($index->getColumns()) > 1,
                'flags'       => $index->getFlags(),
                'options'     => $index->getOptions(),
            ];
        }
    }

    public static function getType($index)
    {
        // Handle IndexObject
        if (is_object($index) && method_exists($index, 'isPrimary') && $index->isPrimary()) {
            return static::PRIMARY;
        } elseif (is_object($index) && method_exists($index, 'isUnique') && $index->isUnique()) {
            return static::UNIQUE;
        }
        
        // Handle array
        if (isset($index['is_primary']) && $index['is_primary']) {
            return static::PRIMARY;
        } elseif (isset($index['is_unique']) && $index['is_unique']) {
            return static::UNIQUE;
        } else {
            return static::INDEX;
        }
    }

    /**
     * Create a default index name.
     *
     * @param array  $columns
     * @param string $type
     * @param string $table
     *
     * @return string
     */
    public static function createName(array $columns, $type, $table = null)
    {
        $table = isset($table) ? trim($table).'_' : '';
        $type = trim($type);
        $name = strtolower($table.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $name);
    }

    public static function availableTypes()
    {
        return [
            static::PRIMARY,
            static::UNIQUE,
            static::INDEX,
        ];
    }
}

class IndexObject
{
    protected $name;
    protected $columns;
    protected $isUnique;
    protected $isPrimary;
    protected $flags;
    protected $options;

    public function __construct($name, $columns, $isUnique, $isPrimary, $flags, $options)
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->isUnique = $isUnique;
        $this->isPrimary = $isPrimary;
        $this->flags = $flags;
        $this->options = $options;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function isUnique()
    {
        return $this->isUnique;
    }

    public function isPrimary()
    {
        return $this->isPrimary;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
