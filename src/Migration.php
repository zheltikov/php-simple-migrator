<?php

declare(strict_types=1);

namespace Zheltikov\SimpleMigrator;

class Migration
{
    public function __construct(
        protected string $id,
        protected string $up,
        protected string $down,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUp(): string
    {
        return $this->up;
    }

    public function getDown(): string
    {
        return $this->down;
    }
}
