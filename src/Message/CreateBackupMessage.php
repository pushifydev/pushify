<?php

namespace App\Message;

class CreateBackupMessage
{
    public function __construct(
        private int $backupId
    ) {
    }

    public function getBackupId(): int
    {
        return $this->backupId;
    }
}
