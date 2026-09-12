<?php

namespace TCG\Voyager\Database\Types\Mysql;

use TCG\Voyager\Database\Types\Type;

class LongTextType extends Type
{
    public const NAME = 'longtext';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        return 'longtext';
    }
}
