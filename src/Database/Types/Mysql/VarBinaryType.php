<?php

namespace TCG\Voyager\Database\Types\Mysql;

use TCG\Voyager\Database\Types\Type;

class VarBinaryType extends Type
{
    public const NAME = 'varbinary';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        $field['length'] = empty($field['length']) ? 255 : $field['length'];

        return "varbinary({$field['length']})";
    }
}
