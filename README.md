# php-simple-migrator

A simple database migration tool.

## Usage

First, install this package via [Composer](https://getcomposer.org):

```shell
$ composer require zheltikov/simple-migrator
```

Then, define a configuration file in your project, for example `migration_config.php`:

```php
<?php

use Zheltikov\SimpleMigrator\{Config, Migration, MigrationSet};

return new Config(
    // Supply a PDO object here to connect to the database
    PDO: new PDO('pgsql:host=localhost;port=5432;dbname=postgres;user=postgres;password=secret'),
    
    // Define your migrations here...
    migrationSet: new MigrationSet([
        new Migration(
            id: '1',
            up: 'CREATE TABLE people (id INTEGER);',
            down: 'DROP TABLE people;',
        ),
        new Migration(
            id: '2',
            up: 'ALTER TABLE people ADD COLUMN name VARCHAR;',
            down: 'ALTER TABLE people DROP COLUMN name;',
        ),
        // ...
    ]),
    
    // Optionally, change the name of the migration log table.
    // By default, it is 'migrations'.
    tableName: 'my_migrations',
);
```

Then, you can apply these migrations by running:

```shell
$ vendor/bin/simple-migrator latest migration_config.php
```

Alternatively, check out the other commands to apply the migrations one-by-one:

```shell
$ vendor/bin/simple-migrator 

Usage: vendor/bin/simple-migrator COMMAND CONFIG_FILE

COMMAND is one of the following actions:
    up             Apply the next migration
    down           Revert to the previous migration
    latest         Apply all migrations up to the latest one
    current        Print the ID of the migration currently applied to the database

CONFIG_FILE points to the migration config file.
```

## Features

- [X] PostgreSQL support (via PDO)
- [ ] MySQL support
- [ ] SQLite support
