<?php

namespace TCG\Voyager\Database\Types\Mysql;

use TCG\Voyager\Database\Types\Type;

class BitType extends Type
{
    public const NAME = 'bit';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        $length = empty($field['length']) ? 1 : $field['length'];
        $length = $length > 64 ? 64 : $length;

        return "bit({$length})";
    }
}
