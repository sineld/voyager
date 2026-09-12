<?php

namespace TCG\Voyager\Database\Schema;

use TCG\Voyager\Database\Types\Type;

class Column
{
    protected $name;
    protected $type;
    protected $options = [];

    public function __construct($name, $type, $options = [])
    {
        $this->name = $name;
        
        // Convert string type to Type object
        if (is_string($type)) {
            $typeObj = Type::getType($type);
            if (!$typeObj) {
                throw new \RuntimeException("Type {$type} not found");
            }
            $type = $typeObj;
        }
        
        $this->type = $type;
        $this->options = $options;
    }

    public static function make(array $column, ?string $tableName = null)
    {
        $name = Identifier::validate($column['name'], 'Column');
        $type = $column['type'];
        
        // For Laravel 12 compatibility, use our custom Type system
        if (is_array($type)) {
            $typeName = trim($type['name']);
            $typeObj = Type::getType($typeName);
            if (!$typeObj) {
                throw new \RuntimeException("Type {$typeName} not found");
            }
            $type = $typeObj;
        } elseif (is_string($type)) {
            // Handle string type names
            $typeObj = Type::getType($type);
            if (!$typeObj) {
                throw new \RuntimeException("Type {$type} not found");
            }
            $type = $typeObj;
        }
        
        if (is_object($type)) {
            $type->tableName = $tableName;
        }

        $options = array_diff_key($column, array_flip(['name', 'composite', 'oldName', 'null', 'extra', 'type', 'charset', 'collation']));

        if (array_key_exists('default', $options)) {
            $options['default'] = static::normalizeDefault($options['default']);
        }

        // Laravel's schema reader speaks `nullable`/`auto_increment`; Voyager's editor
        // speaks `notnull`/`autoincrement`. Normalize so both sources agree.
        if (array_key_exists('nullable', $options) && !array_key_exists('notnull', $options)) {
            $options['notnull'] = !$options['nullable'];
        }

        if (array_key_exists('auto_increment', $options) && !array_key_exists('autoincrement', $options)) {
            $options['autoincrement'] = (bool) $options['auto_increment'];
        }

        return new self($name, $type, $options);
    }

    /**
     * Drivers report column defaults as SQL literals ("'voyager admin'", "(now())").
     * Strip the quoting so the editor shows the value itself and re-saving does not
     * wrap it in another pair of quotes every time.
     */
    protected static function normalizeDefault($default)
    {
        if (!is_string($default)) {
            return $default;
        }

        $value = trim($default);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                $value = substr($value, 1, -1);

                // Un-escape the doubled quotes SQL uses inside literals.
                $value = str_replace($first.$first, $first, $value);
            }
        }

        return $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getAutoincrement()
    {
        return $this->options['autoincrement'] ?? false;
    }

    public function getNotnull()
    {
        return (bool) ($this->options['notnull'] ?? false);
    }

    public function getDefault()
    {
        return $this->options['default'] ?? null;
    }

    public function getLength()
    {
        return $this->options['length'] ?? null;
    }

    public function getPrecision()
    {
        return $this->options['precision'] ?? null;
    }

    public function getScale()
    {
        return $this->options['scale'] ?? null;
    }

    public function getUnsigned()
    {
        return (bool) ($this->options['unsigned'] ?? false);
    }

    public function getComment()
    {
        return $this->options['comment'] ?? null;
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public static function toArray($column)
    {
        $columnArr = [
            'name' => $column->getName(),
            'type' => Type::toArray($column->getType()),
            'oldName' => $column->getName(),
            'null' => $column->getNotnull() ? 'NO' : 'YES',
            'extra' => static::getExtra($column),
            'composite' => false,
        ];

        // Merge with options
        $columnArr = array_merge($columnArr, $column->getOptions());

        return $columnArr;
    }

    /**
     * @return string
     */
    protected static function getExtra($column)
    {
        $extra = '';

        $extra .= $column->getAutoincrement() ? 'auto_increment' : '';
        // todo: Add Extra stuff like mysql 'onUpdate' etc...

        return $extra;
    }
}
