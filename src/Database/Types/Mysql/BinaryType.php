<?php

namespace TCG\Voyager\Database\Types\Mysql;

use TCG\Voyager\Database\Types\Type;

class BinaryType extends Type
{
    public const NAME = 'binary';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        $field['length'] = empty($field['length']) ? 255 : $field['length'];

        return "binary({$field['length']})";
    }
}
