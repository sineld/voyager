<?php

namespace TCG\Voyager\Database\Types;


use TCG\Voyager\Database\Platforms\Platform;
use TCG\Voyager\Database\Schema\SchemaManager;

abstract class Type
{
    protected static $customTypesRegistered = false;
    protected static $platformTypeMapping = [];
    protected static $allTypes = [];
    protected static $allTypeNames = null;
    protected static $platformTypes = [];
    protected static $customTypeOptions = [];
    protected static $typeCategories = [];

    /**
     * Options attached to a concrete type instance by registerCustomPlatformTypes().
     *
     * Declared explicitly: PHP 8.2 deprecated dynamic properties and PHP 9 removes them.
     *
     * @var array
     */
    public $customOptions = [];

    /**
     * Table the type was resolved for; EnumType needs it to read the allowed values.
     *
     * @var string|null
     */
    public $tableName = null;

    public const NAME = 'UNDEFINED_TYPE_NAME';
    public const NOT_SUPPORTED = 'notSupported';
    public const NOT_SUPPORT_INDEX = 'notSupportIndex';

    // todo: make sure this is not overwrting DoctrineType properties

    // Note: length, precision and scale need default values manually

    public function getName()
    {
        return static::NAME;
    }

    public static function hasType($name)
    {
        return isset(static::$allTypes[$name]);
    }

    public static function addType($name, $class)
    {
        static::$allTypes[$name] = new $class();
    }

    public static function overrideType($name, $class)
    {
        static::$allTypes[$name] = new $class();
    }

    public static function getType($name)
    {
        // Ensure basic types are registered first
        if (empty(static::$allTypes)) {
            static::registerBasicTypes();
        }
                
        // Handle types with length specifiers like varchar(255), int(10), etc.
        if (preg_match('/^([a-zA-Z]+)\([^)]+\)/', $name, $matches)) {
            $baseType = $matches[1];
            
            // Map common type variations
            if ($baseType === 'int') {
                $baseType = 'integer';
            } elseif ($baseType === 'bool') {
                $baseType = 'boolean';
            } elseif ($baseType === 'bigint') {
                $baseType = 'integer';
            }
            
            if (isset(static::$allTypes[$baseType])) {
                return static::$allTypes[$baseType];
            }
        }
        
        // Handle unsigned integer types by mapping them to regular integer
        if (strpos($name, 'unsigned') !== false) {
            // Handle cases like "bigint(20) unsigned" - remove both length specifiers and unsigned
            $baseType = preg_replace('/\s*unsigned\s*/i', '', $name);
            $baseType = preg_replace('/\([^)]+\)/', '', $baseType); // Remove length specifiers like (10)
            $baseType = trim($baseType);
            
            if ($baseType === 'int') {
                $baseType = 'integer';
            }
            
            if (isset(static::$allTypes[$baseType])) {
                return static::$allTypes[$baseType];
            }
        }
        
        return static::$allTypes[$name] ?? null;
    }

    protected static function registerBasicTypes()
    {
        // Register common basic types
        $basicTypes = [
            'integer' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'int' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'tinyint' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'smallint' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'mediumint' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'bigint' => \TCG\Voyager\Database\Types\Common\IntegerType::class,
            'string' => \TCG\Voyager\Database\Types\Common\StringType::class,
            'varchar' => \TCG\Voyager\Database\Types\Common\VarCharType::class,
            'text' => \TCG\Voyager\Database\Types\Common\TextType::class,
            'boolean' => \TCG\Voyager\Database\Types\Common\BooleanType::class,
            'datetime' => \TCG\Voyager\Database\Types\Common\DateTimeType::class,
            'date' => \TCG\Voyager\Database\Types\Common\DateType::class,
            'time' => \TCG\Voyager\Database\Types\Common\TimeType::class,
            'float' => \TCG\Voyager\Database\Types\Common\FloatType::class,
            'double' => \TCG\Voyager\Database\Types\Common\DoubleType::class,
            'decimal' => \TCG\Voyager\Database\Types\Common\DecimalType::class,
            'numeric' => \TCG\Voyager\Database\Types\Common\NumericType::class,
            'json' => \TCG\Voyager\Database\Types\Common\JsonType::class,
            'char' => \TCG\Voyager\Database\Types\Common\CharType::class,
        ];

        foreach ($basicTypes as $name => $class) {
            if (!static::hasType($name) && class_exists($class)) {
                static::addType($name, $class);
            }
        }
    }

    public static function toArray($type)
    {
        $customTypeOptions = $type->customOptions ?? [];

        return array_merge([
            'name' => $type->getName(),
        ], $customTypeOptions);
    }

    public static function getPlatformTypes()
    {
        if (static::$platformTypes) {
            return static::$platformTypes;
        }

        if (!static::$customTypesRegistered) {
            static::registerCustomPlatformTypes();
        }

        $platformName = SchemaManager::getDatabasePlatformName();

        static::$platformTypes = Platform::getPlatformTypes(
            $platformName,
            static::getPlatformTypeMapping($platformName)
        );

        static::$platformTypes = static::$platformTypes->map(function ($type) {
            return static::toArray(static::getType($type));
        })->groupBy('category');

        return static::$platformTypes;
    }

    public static function getPlatformTypeMapping($platformName)
    {
        if (static::$platformTypeMapping) {
            return static::$platformTypeMapping;
        }

        // For Laravel 12 compatibility, return empty collection
        // since Doctrine type mapping is no longer available
        static::$platformTypeMapping = collect([]);

        return static::$platformTypeMapping;
    }

    public static function registerCustomPlatformTypes($force = false)
    {
        if (static::$customTypesRegistered && !$force) {
            return;
        }

        // Ensure basic types are registered first
        static::registerBasicTypes();

        $platformName = ucfirst(SchemaManager::getDatabasePlatformName());

        $customTypes = array_merge(
            static::getPlatformCustomTypes('Common'),
            static::getPlatformCustomTypes($platformName)
        );

        foreach ($customTypes as $type) {
            $name = $type::NAME;

            if (static::hasType($name)) {
                static::overrideType($name, $type);
            } else {
                static::addType($name, $type);
            }

            $dbType = defined("{$type}::DBTYPE") ? $type::DBTYPE : $name;

            // Doctrine type mapping is no longer supported in Laravel 12
            // $platform->registerDoctrineTypeMapping($dbType, $name);
        }

        static::addCustomTypeOptions($platformName);

        static::$customTypesRegistered = true;
    }

    protected static function addCustomTypeOptions($platformName)
    {
        static::registerCommonCustomTypeOptions();

        Platform::registerPlatformCustomTypeOptions($platformName);

        // Add the custom options to the types
        foreach (static::$customTypeOptions as $option) {
            foreach ($option['types'] as $type) {
                if (static::hasType($type)) {
                    static::getType($type)->customOptions[$option['name']] = $option['value'];
                }
            }
        }
    }

    protected static function getPlatformCustomTypes($platformName)
    {
        $typesPath = __DIR__.DIRECTORY_SEPARATOR.$platformName.DIRECTORY_SEPARATOR;
        $namespace = __NAMESPACE__.'\\'.$platformName.'\\';
        $types = [];

        foreach (glob($typesPath.'*.php') as $classFile) {
            $className = str_replace(
                '.php',
                '',
                str_replace($typesPath, '', $classFile)
            );
            
            // Skip BasicTypes as it's not a data type but a registrar
            if ($className === 'BasicTypes') {
                continue;
            }
            
            $types[] = $namespace.$className;
        }

        return $types;
    }

    public static function registerCustomOption($name, $value, $types)
    {
        if (is_string($types)) {
            $types = trim($types);

            if ($types == '*') {
                $types = static::getAllTypes()->toArray();
            } elseif (strpos($types, '*') !== false) {
                $searchType = str_replace('*', '', $types);
                $types = static::getAllTypes()->filter(function ($type) use ($searchType) {
                    return strpos($type, $searchType) !== false;
                })->toArray();
            } else {
                $types = [$types];
            }
        }

        static::$customTypeOptions[] = [
            'name'  => $name,
            'value' => $value,
            'types' => $types,
        ];
    }

    protected static function registerCommonCustomTypeOptions()
    {
        static::registerTypeCategories();
        static::registerTypeDefaultOptions();
    }

    protected static function registerTypeDefaultOptions()
    {
        $types = static::getTypeCategories();

        // Numbers
        static::registerCustomOption('default', [
            'type' => 'number',
            'step' => 'any',
        ], $types['numbers']);

        // Date and Time
        static::registerCustomOption('default', [
            'type' => 'date',
        ], 'date');
        static::registerCustomOption('default', [
            'type' => 'time',
            'step' => '1',
        ], 'time');
        static::registerCustomOption('default', [
            'type' => 'number',
            'min'  => '0',
        ], 'year');
    }

    protected static function registerTypeCategories()
    {
        $types = static::getTypeCategories();

        static::registerCustomOption('category', 'Numbers', $types['numbers']);
        static::registerCustomOption('category', 'Strings', $types['strings']);
        static::registerCustomOption('category', 'Date and Time', $types['datetime']);
        static::registerCustomOption('category', 'Lists', $types['lists']);
        static::registerCustomOption('category', 'Binary', $types['binary']);
        static::registerCustomOption('category', 'Geometry', $types['geometry']);
        static::registerCustomOption('category', 'Network', $types['network']);
        static::registerCustomOption('category', 'Objects', $types['objects']);
    }

    public static function getAllTypes()
    {
        if (static::$allTypeNames !== null) {
            return static::$allTypeNames;
        }

        static::$allTypeNames = collect(static::getTypeCategories())->flatten();

        return static::$allTypeNames;
    }

    public static function getTypeCategories()
    {
        if (static::$typeCategories) {
            return static::$typeCategories;
        }

        $numbers = [
            'boolean',
            'tinyint',
            'smallint',
            'mediumint',
            'integer',
            'int',
            'bigint',
            'decimal',
            'numeric',
            'money',
            'float',
            'real',
            'double',
            'double precision',
        ];

        $strings = [
            'char',
            'character',
            'varchar',
            'character varying',
            'string',
            'guid',
            'uuid',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
            'tsquery',
            'tsvector',
            'xml',
        ];

        $datetime = [
            'date',
            'datetime',
            'year',
            'time',
            'timetz',
            'timestamp',
            'timestamptz',
            'datetimetz',
            'dateinterval',
            'interval',
        ];

        $lists = [
            'enum',
            'set',
            'simple_array',
            'array',
            'json',
            'jsonb',
            'json_array',
        ];

        $binary = [
            'bit',
            'bit varying',
            'binary',
            'varbinary',
            'tinyblob',
            'blob',
            'mediumblob',
            'longblob',
            'bytea',
        ];

        $network = [
            'cidr',
            'inet',
            'macaddr',
            'txid_snapshot',
        ];

        $geometry = [
            'geometry',
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometrycollection',
        ];

        $objects = [
            'object',
        ];

        static::$typeCategories = [
            'numbers'  => $numbers,
            'strings'  => $strings,
            'datetime' => $datetime,
            'lists'    => $lists,
            'binary'   => $binary,
            'network'  => $network,
            'geometry' => $geometry,
            'objects'  => $objects,
        ];

        return static::$typeCategories;
    }
}
