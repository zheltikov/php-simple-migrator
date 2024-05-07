<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

use PDO;

class Config
{
    public function __construct(
        protected PDO $PDO,
        protected MigrationSet $migrationSet,
        protected string $tableName = 'migrations',
    ) {
    }

    public function getPDO(): PDO
    {
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
}
