<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

use Closure;
use PDO;

class Config
{
    public function __construct(
        protected PDO|Closure $PDO,
        protected MigrationSet $migrationSet,
        protected string $tableName = 'migrations',
        protected string $dialect = Dialect::POSTGRESQL,
    ) {
        // FIXME: maybe validate somewhere else?
        Dialect::from($this->dialect);
    }

    public function getPDO(): PDO
    {
        if (is_callable($this->PDO)) {
            $this->PDO = ($this->PDO)();
        }

        return $this->PDO;
    }

    public function getMigrationSet(): MigrationSet
    {
        return $this->migrationSet;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getDialect(): string
    {
        return $this->dialect;
    }
}
