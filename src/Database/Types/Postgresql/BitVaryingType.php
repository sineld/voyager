<?php

namespace TCG\Voyager\Database\Types\Postgresql;

use TCG\Voyager\Database\Types\Type;

class BitVaryingType extends Type
{
    public const NAME = 'bit varying';
    public const DBTYPE = 'varbit';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        $length = empty($field['length']) ? 255 : $field['length'];

        return "varbit({$length})";
    }
}
