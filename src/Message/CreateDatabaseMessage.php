<?php

namespace App\Message;

class CreateDatabaseMessage
{
    public function __construct(
        private int $databaseId
    ) {
    }

    public function getDatabaseId(): int
    {
        return $this->databaseId;
    }
}
