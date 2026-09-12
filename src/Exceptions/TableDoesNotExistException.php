<?php

namespace TCG\Voyager\Exceptions;

use RuntimeException;

/**
 * Replaces Doctrine\DBAL\Schema\SchemaException::tableDoesNotExist(), which went
 * away with doctrine/dbal in Laravel 11.
 */
class TableDoesNotExistException extends RuntimeException
{
    public function __construct(protected string $table)
    {
        parent::__construct("There is no table with name '{$table}' in the schema.");
    }

    public static function make(string $table): self
    {
        return new self($table);
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
