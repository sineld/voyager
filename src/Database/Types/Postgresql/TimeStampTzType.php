<?php

namespace TCG\Voyager\Database\Types\Postgresql;

use TCG\Voyager\Database\Types\Type;

class TimeStampTzType extends Type
{
    public const NAME = 'timestamptz';

    public function getSQLDeclaration(array $field, $platform = null)
    {
        return 'timestamp(0) with time zone';
    }
}
