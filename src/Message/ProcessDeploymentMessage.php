<?php

namespace App\Message;

class ProcessDeploymentMessage
{
    public function __construct(
        private int $deploymentId
    ) {
    }

    public function getDeploymentId(): int
    {
        return $this->deploymentId;
    }
}
