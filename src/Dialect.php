<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

use Exception;

abstract class Dialect
{
    public const POSTGRESQL = 'POSTGRESQL';
    public const SQLITE = 'SQLITE';

    /**
     * @throws Exception
     */
    public static function from(mixed $value): string
    {
        return match ($value) {
            static::POSTGRESQL => static::POSTGRESQL,
            static::SQLITE => static::SQLITE,
            default => throw new Exception(sprintf('Invalid Sql Dialect %s', var_export($value, true))),
        };
    }
}
