<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

use Countable;

class MigrationSet implements Countable
{
    /**
     * @param Migration[] $migrations
     */
    public function __construct(
        protected array $migrations,
    ) {
    }

    /**
     * @return Migration[]
     */
    public function getMigrations(): array
    {
        return $this->migrations;
    }

    public function count(): int
    {
        return count($this->migrations);
    }

    public function getFirstMigration(): ?Migration
    {
        foreach ($this->migrations as $migration) {
            return $migration;
        }

        return null;
    }

    public function getMigration(?string $id): ?Migration
    {
        if ($id === null) {
            return null;
        }

        foreach ($this->migrations as $migration) {
            if ($migration->getId() === $id) {
                return $migration;
            }
        }

        return null;
    }

    public function getNextMigration(?string $id): ?Migration
    {
        if ($id === null) {
            return null;
        }

        $found = false;
        foreach ($this->migrations as $migration) {
            if ($found) {
                return $migration;
            }

            if ($migration->getId() === $id) {
                $found = true;
            }
        }

        return null;
    }

    public function getPreviousMigration(?string $id): ?Migration
    {
        if ($id === null) {
            return null;
        }

        $previous = null;
        foreach ($this->migrations as $migration) {
            if ($migration->getId() === $id) {
                return $previous;
            }

            $previous = $migration;
        }

        return null;
    }
}
