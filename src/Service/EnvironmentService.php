<?php

namespace App\Service;

use App\Entity\EnvironmentVariable;
use App\Entity\Project;
use App\Repository\EnvironmentVariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EnvironmentService
{
    private string $encryptionKey;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EnvironmentVariableRepository $envVarRepository,
        private LoggerInterface $logger,
        string $appSecret
    ) {
        // Derive a 32-byte encryption key from APP_SECRET
        $this->encryptionKey = hash('sha256', $appSecret, true);
    }

    /**
     * Encrypt a value using Sodium
     */
    public function encrypt(string $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($value, $nonce, $this->encryptionKey);

        // Combine nonce and encrypted data, then base64 encode
        return base64_encode($nonce . $encrypted);
    }

    /**
     * Decrypt a value using Sodium
     */
    public function decrypt(string $encryptedValue): string
    {
        $decoded = base64_decode($encryptedValue);

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);

        if ($decrypted === false) {
            throw new \RuntimeException('Failed to decrypt environment variable');
        }

        return $decrypted;
    }

    /**
     * Get all environment variables for a project (decrypted)
     *
     * @return array<string, string>
     */
    public function getProjectEnvVars(Project $project): array
    {
        $envVars = $this->envVarRepository->findByProject($project);
        $result = [];

        foreach ($envVars as $envVar) {
            try {
                $result[$envVar->getKey()] = $this->decrypt($envVar->getValue());
            } catch (\Exception $e) {
                $this->logger->error('Failed to decrypt env var', [
                    'project' => $project->getId(),
                    'key' => $envVar->getKey(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $result;
    }

    /**
     * Get all environment variables for a project (with metadata)
     *
     * @return array
     */
    public function getProjectEnvVarsWithMeta(Project $project): array
    {
        $envVars = $this->envVarRepository->findByProject($project);
        $result = [];

        foreach ($envVars as $envVar) {
            try {
                $value = $this->decrypt($envVar->getValue());

                $result[] = [
                    'id' => $envVar->getId(),
                    'key' => $envVar->getKey(),
                    'value' => $envVar->isSecret() ? '••••••••' : $value,
                    'actualValue' => $value, // For editing
                    'isSecret' => $envVar->isSecret(),
                    'createdAt' => $envVar->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $envVar->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ];
            } catch (\Exception $e) {
                $this->logger->error('Failed to decrypt env var', [
                    'project' => $project->getId(),
                    'key' => $envVar->getKey(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $result;
    }

    /**
     * Create or update an environment variable
     */
    public function createOrUpdate(Project $project, string $key, string $value, bool $isSecret = false): EnvironmentVariable
    {
        // Validate key format (alphanumeric and underscores only)
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException('Environment variable key must contain only uppercase letters, numbers, and underscores, and must not start with a number');
        }

        $envVar = $this->envVarRepository->findOneByProjectAndKey($project, $key);

        if (!$envVar) {
            $envVar = new EnvironmentVariable();
            $envVar->setProject($project);
            $envVar->setKey($key);
        }

        $encryptedValue = $this->encrypt($value);
        $envVar->setValue($encryptedValue);
        $envVar->setIsSecret($isSecret);

        $this->entityManager->persist($envVar);
        $this->entityManager->flush();

        $this->logger->info('Environment variable saved', [
            'project' => $project->getId(),
            'key' => $key,
            'isSecret' => $isSecret
        ]);

        return $envVar;
    }

    /**
     * Delete an environment variable
     */
    public function delete(EnvironmentVariable $envVar): void
    {
        $this->entityManager->remove($envVar);
        $this->entityManager->flush();

        $this->logger->info('Environment variable deleted', [
            'id' => $envVar->getId(),
            'key' => $envVar->getKey()
        ]);
    }

    /**
     * Export environment variables to .env file format
     */
    public function exportToEnvFile(Project $project): string
    {
        $envVars = $this->getProjectEnvVars($project);
        $lines = [];

        $lines[] = '# Environment variables for ' . $project->getName();
        $lines[] = '# Generated at ' . (new \DateTime())->format('Y-m-d H:i:s');
        $lines[] = '';

        foreach ($envVars as $key => $value) {
            // Escape value if it contains spaces or special characters
            if (preg_match('/[\s"\'$]/', $value)) {
                $value = '"' . addslashes($value) . '"';
            }
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * Import environment variables from .env file content
     *
     * @return array{success: int, errors: array}
     */
    public function importFromEnvFile(Project $project, string $content, bool $overwrite = false): array
    {
        $lines = explode("\n", $content);
        $success = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE format
            if (!str_contains($line, '=')) {
                $errors[] = "Line " . ($lineNumber + 1) . ": Invalid format (missing =)";
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes from value if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
                $value = stripslashes($value);
            }

            // Check if key already exists
            if (!$overwrite && $this->envVarRepository->findOneByProjectAndKey($project, $key)) {
                $errors[] = "Line " . ($lineNumber + 1) . ": Key '$key' already exists (use overwrite option)";
                continue;
            }

            try {
                $this->createOrUpdate($project, $key, $value);
                $success++;
            } catch (\Exception $e) {
                $errors[] = "Line " . ($lineNumber + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'errors' => $errors
        ];
    }

    /**
     * Generate Docker environment variables for deployment
     * Returns array in format suitable for docker run -e flags
     *
     * @return array<string>
     */
    public function getDockerEnvFlags(Project $project): array
    {
        $envVars = $this->getProjectEnvVars($project);
        $flags = [];

        foreach ($envVars as $key => $value) {
            // Escape for shell
            $escapedValue = escapeshellarg($value);
            $flags[] = "-e {$key}={$escapedValue}";
        }

        return $flags;
    }
}
