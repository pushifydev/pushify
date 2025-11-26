<?php

namespace App\Twig\Runtime;

class VersionRuntime
{
    private ?string $version = null;

    public function __construct(private string $projectDir)
    {
    }

    public function __toString(): string
    {
        if ($this->version === null) {
            $versionFile = $this->projectDir . '/VERSION';

            if (file_exists($versionFile)) {
                $this->version = trim(file_get_contents($versionFile));
            } else {
                $this->version = '0.1.0-beta';
            }
        }

        return $this->version;
    }
}
