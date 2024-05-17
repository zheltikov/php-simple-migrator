<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PDO;
use Throwable;

class Migrator
{
    public function __construct(
        protected Config $config,
    ) {
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Apply the next migration.
     */
    public function up(): int
    {
        if ($this->config->getMigrationSet()->count() === 0) {
            fprintf(STDERR, "No migrations in the set.\n");
            return 0;
        }

        $this->startTransaction();
        try {
            $this->setup();

            $currentId = $this->getCurrentMigrationId();
            if ($currentId === null) {
                $next = $this->config->getMigrationSet()->getFirstMigration();
            } else {
                $next = $this->config->getMigrationSet()->getNextMigration($currentId);
            }

            if ($next === null) {
                fprintf(STDERR, "No migration to apply.\n");
                $this->rollbackTransaction();
                return 0;
            }

            try {
                $this->executeSql($next->getUp());
            } catch (Throwable $e) {
                fprintf(
                    STDERR,
                    "Failed to execute migration %s upwards: %s.\n",
                    var_export($next->getId(), true),
                    $e->getMessage(),
                );
                $this->rollbackTransaction();
                return (int) $e->getCode() ?: 1;
            }

            $this->setCurrentMigrationId($next->getId());
            $this->commitTransaction();

            fprintf(STDERR, "Successfully applied migration %s.\n", var_export($next->getId(), true));

            return 0;
        } catch (Throwable $e) {
            fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
            $this->rollbackTransaction();
            return (int) $e->getCode() ?: 1;
        }
    }

    /**
     * Revert to the previous migration.
     */
    public function down(): int
    {
        if ($this->config->getMigrationSet()->count() === 0) {
            fprintf(STDERR, "No migrations in the set.\n");
            return 0;
        }

        $this->startTransaction();
        try {
            $this->setup();

            $currentId = $this->getCurrentMigrationId();
            if ($currentId === null) {
                fprintf(STDERR, "No migration currently applied, nothing to do.\n");
                $this->rollbackTransaction();
                return 0;
            }

            $current = $this->config->getMigrationSet()->getMigration($currentId);
            if ($current === null) {
                fprintf(STDERR, "ERROR: Current migration not found, check your config file.\n");
                $this->rollbackTransaction();
                return 1;
            }

            try {
                $this->executeSql($current->getDown());
            } catch (Throwable $e) {
                fprintf(
                    STDERR,
                    "Failed to execute migration %s downwards: %s.\n",
                    var_export($current->getId(), true),
                    $e->getMessage(),
                );
                $this->rollbackTransaction();
                return (int) $e->getCode() ?: 1;
            }

            $previous = $this->config->getMigrationSet()->getPreviousMigration($currentId);
            $this->setCurrentMigrationId($previous?->getId());

            $this->commitTransaction();

            fprintf(STDERR, "Successfully reverted migration %s.\n", var_export($current->getId(), true));

            return 0;
        } catch (Throwable $e) {
            fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
            $this->rollbackTransaction();
            return (int) $e->getCode() ?: 1;
        }
    }

    /**
     * Apply all migrations up to the latest one.
     */
    public function latest(): int
    {
        if ($this->config->getMigrationSet()->count() === 0) {
            fprintf(STDERR, "No migrations in the set.\n");
            return 0;
        }

        $this->startTransaction();
        try {
            $this->setup();

            $okCount = 0;
            while (true) {
                $currentId = $this->getCurrentMigrationId();
                if ($currentId === null) {
                    $next = $this->config->getMigrationSet()->getFirstMigration();
                } else {
                    $next = $this->config->getMigrationSet()->getNextMigration($currentId);
                }

                if ($next === null) {
                    break;
                }

                try {
                    $this->executeSql($next->getUp());
                } catch (Throwable $e) {
                    fprintf(
                        STDERR,
                        "Failed to execute migration %s upwards: %s.\n",
                        var_export($next->getId(), true),
                        $e->getMessage(),
                    );
                    $this->rollbackTransaction();
                    return (int) $e->getCode() ?: 1;
                }

                $this->setCurrentMigrationId($next->getId());
                $okCount += 1;
            }

            if ($okCount === 0) {
                fprintf(STDERR, "No migrations to apply.\n");
                $this->rollbackTransaction();
            } else {
                fprintf(STDERR, "Applied %d migrations.\n", $okCount);
                $this->commitTransaction();
            }

            return 0;
        } catch (Throwable $e) {
            fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
            $this->rollbackTransaction();
            return (int) $e->getCode() ?: 1;
        }
    }

    public function current(): int
    {
        if ($this->config->getMigrationSet()->count() === 0) {
            fprintf(STDERR, "No migrations in the set.\n");
            return 0;
        }

        $this->startTransaction();
        try {
            $this->setup();
            $currentId = $this->getCurrentMigrationId();
            $this->commitTransaction();

            fprintf(STDERR, "Migration %s is currently applied.\n", var_export($currentId, true));

            return 0;
        } catch (Throwable $e) {
            fprintf(STDERR, "ERROR: %s\n", $e->getMessage());
            $this->rollbackTransaction();
            return (int) $e->getCode() ?: 1;
        }
    }

    /**
     * Return the ID of the migration currently applied to the database.
     *
     * @throws Throwable
     */
    protected function getCurrentMigrationId(): ?string
    {
        $rows = $this->executeSql(
            sql: $this->sqlSelectMigrationId(),
        );

        foreach ($rows ?? [] as $row) {
            return $row['id'];
        }

        return null;
    }

    protected function sqlSelectMigrationId(): string
    {
        return match ($this->config->getDialect()) {
            Dialect::POSTGRESQL => /** @lang PostgreSQL */ "
                SELECT id
                  FROM {$this->config->getTableName()}
                 ORDER BY timestamp DESC
                 LIMIT 1
                   FOR UPDATE;
            ",
            Dialect::SQLITE => /** @lang SQLite */ "
                SELECT id
                  FROM {$this->config->getTableName()}
                 ORDER BY timestamp DESC
                 LIMIT 1;
            ",
        };
    }

    /**
     * Saves the ID of the migration currently applied to the database.
     *
     * @throws Throwable
     */
    protected function setCurrentMigrationId(?string $id): void
    {
        $this->executeSql(
            sql: $this->sqlInsertMigrationId(),
            params: [
                'id'        => $id,
                'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
            ],
        );
    }

    protected function sqlInsertMigrationId(): string
    {
        return match ($this->config->getDialect()) {
            Dialect::POSTGRESQL => /** @lang PostgreSQL */ "
                INSERT INTO {$this->config->getTableName()} (id, timestamp)
                VALUES (:id, :timestamp);
            ",
            Dialect::SQLITE => /** @lang SQLite */ "
                INSERT INTO {$this->config->getTableName()} (id, timestamp)
                VALUES (:id, :timestamp);
            ",
        };
    }

    /**
     * Sets up the migration records table.
     *
     * @throws Throwable
     */
    protected function setup(): void
    {
        try {
            $rows = $this->executeSql(
                sql: $this->sqlCheckTable(),
                params: [
                    'table' => $this->config->getTableName(),
                ],
            );

            foreach ($rows as $row) {
                if ($row['ok'] === null) {
                    throw new Exception(
                        sprintf(
                            'Table %d does not exist',
                            var_export($this->config->getTableName(), true),
                        )
                    );
                }

                break;
            }
        } catch (Throwable) {
            fprintf(STDERR, "Creating %s table...\n", var_export($this->config->getTableName(), true));
            $this->executeSql(
                sql: $this->sqlCreateTable(),
            );
        }
    }

    protected function sqlCheckTable(): string
    {
        return match ($this->config->getDialect()) {
            Dialect::POSTGRESQL => /** @lang PostgreSQL */ "
                SELECT to_regclass(:table) AS ok
                   FOR UPDATE;
            ",
            Dialect::SQLITE => /** @lang SQLite */ "
                SELECT CASE WHEN EXISTS(
                    SELECT name
                      FROM sqlite_master
                     WHERE type = 'table' AND name = :table
                ) THEN 'ok' ELSE NULL END;
            ",
        };
    }

    protected function sqlCreateTable(): string
    {
        return match ($this->config->getDialect()) {
            Dialect::POSTGRESQL => /** @lang PostgreSQL */ "
                CREATE TABLE {$this->config->getTableName()} (
                    id        VARCHAR,
                    timestamp TIMESTAMP NOT NULL
                );
            ",
            Dialect::SQLITE => /** @lang SQLite */ "
                CREATE TABLE {$this->config->getTableName()} (
                    id        TEXT,
                    timestamp TEXT NOT NULL
                );
            ",
        };
    }

    protected function startTransaction(): void
    {
        $this->config->getPDO()->beginTransaction();
    }

    protected function rollbackTransaction(): void
    {
        $this->config->getPDO()->rollBack();
    }

    protected function commitTransaction(): void
    {
        $this->config->getPDO()->commit();
    }

    /**
     * @throws Throwable
     */
    protected function executeSql(string $sql, ?array $params = null): ?array
    {
        $stmt = $this->config->getPDO()->prepare(
            $sql,
            [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        if ($stmt === false) {
            throw new Exception(
                sprintf(
                    'Failed to prepare statement %s',
                    var_export($sql, true),
                ),
            );
        }

        $ok = $stmt->execute($params);
        if (!$ok) {
            throw new Exception(
                sprintf(
                    'Failed to execute statement %s with params %s',
                    var_export($sql, true),
                    var_export($params, true),
                ),
            );
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: null;
    }
}
