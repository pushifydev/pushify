<?php

namespace App\Service;

class ValidationService
{
    /**
     * Validate server name
     */
    public function validateServerName(string $name): void
    {
        $name = trim($name);

        if (strlen($name) < 3 || strlen($name) > 50) {
            throw new \InvalidArgumentException('Server name must be between 3 and 50 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9-_ ]+$/', $name)) {
            throw new \InvalidArgumentException('Server name can only contain letters, numbers, spaces, hyphens, and underscores');
        }
    }

    /**
     * Validate IP address (IPv4 or IPv6)
     */
    public function validateIpAddress(string $ip): void
    {
        $ip = trim($ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address format');
        }
    }

    /**
     * Validate SSH port number
     */
    public function validatePort(int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }
    }

    /**
     * Validate SSH username
     */
    public function validateSshUsername(string $username): void
    {
        $username = trim($username);

        // Unix username rules: lowercase letters, digits, hyphens, underscores
        // Must start with letter or underscore, max 32 chars
        if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/i', $username)) {
            throw new \InvalidArgumentException('Invalid SSH username format');
        }
    }

    /**
     * Validate SSH private key format
     */
    public function validateSshPrivateKey(string $key): void
    {
        $key = trim($key);

        if (strlen($key) < 100) {
            throw new \InvalidArgumentException('SSH private key is too short');
        }

        // Check for common SSH key headers
        $validHeaders = [
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            '-----BEGIN EC PRIVATE KEY-----',
            '-----BEGIN DSA PRIVATE KEY-----',
        ];

        $hasValidHeader = false;
        foreach ($validHeaders as $header) {
            if (str_starts_with($key, $header)) {
                $hasValidHeader = true;
                break;
            }
        }

        if (!$hasValidHeader) {
            throw new \InvalidArgumentException('Invalid SSH private key format. Must be in PEM format.');
        }

        // Check for footer
        if (!str_contains($key, '-----END') || !str_contains($key, 'PRIVATE KEY-----')) {
            throw new \InvalidArgumentException('Invalid SSH private key format. Missing footer.');
        }
    }

    /**
     * Validate database name
     */
    public function validateDatabaseName(string $name): void
    {
        $name = trim($name);

        if (strlen($name) < 3 || strlen($name) > 50) {
            throw new \InvalidArgumentException('Database name must be between 3 and 50 characters');
        }

        // Must start with letter, can contain letters, numbers, hyphens, underscores
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{2,49}$/', $name)) {
            throw new \InvalidArgumentException(
                'Database name must start with a letter and can only contain letters, numbers, hyphens, and underscores'
            );
        }

        // Prevent reserved names
        $reserved = ['mysql', 'postgres', 'postgresql', 'information_schema', 'performance_schema', 'sys', 'test', 'admin'];
        if (in_array(strtolower($name), $reserved, true)) {
            throw new \InvalidArgumentException('Database name is reserved and cannot be used');
        }
    }

    /**
     * Validate database version for specific type
     */
    public function validateDatabaseVersion(string $type, string $version): void
    {
        $allowedVersions = [
            'postgresql' => ['11', '12', '13', '14', '15', '16', 'latest'],
            'mysql' => ['5.7', '8.0', 'latest'],
            'mariadb' => ['10.5', '10.6', '10.11', '11.0', 'latest'],
            'mongodb' => ['5.0', '6.0', '7.0', 'latest'],
            'redis' => ['6.2', '7.0', '7.2', 'latest'],
        ];

        if (!isset($allowedVersions[$type])) {
            throw new \InvalidArgumentException('Invalid database type: ' . $type);
        }

        if (!in_array($version, $allowedVersions[$type], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid %s version. Allowed versions: %s',
                $type,
                implode(', ', $allowedVersions[$type])
            ));
        }
    }

    /**
     * Validate database username
     */
    public function validateDatabaseUsername(string $username): void
    {
        $username = trim($username);

        if (strlen($username) < 3 || strlen($username) > 32) {
            throw new \InvalidArgumentException('Database username must be between 3 and 32 characters');
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $username)) {
            throw new \InvalidArgumentException(
                'Database username must start with a letter and can only contain letters, numbers, and underscores'
            );
        }

        // Prevent reserved usernames
        $reserved = ['root', 'admin', 'administrator', 'mysql', 'postgres', 'mongodb', 'redis'];
        if (in_array(strtolower($username), $reserved, true)) {
            throw new \InvalidArgumentException('Username is reserved and cannot be used');
        }
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 12) {
            throw new \InvalidArgumentException('Password must be at least 12 characters long');
        }

        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);

        if (!($hasLower && $hasUpper && $hasNumber && $hasSpecial)) {
            throw new \InvalidArgumentException(
                'Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character'
            );
        }
    }

    /**
     * Validate memory size in MB
     */
    public function validateMemorySize(?int $memorySizeMb): void
    {
        if ($memorySizeMb === null) {
            return; // Optional field
        }

        if ($memorySizeMb < 128 || $memorySizeMb > 32768) {
            throw new \InvalidArgumentException('Memory size must be between 128 MB and 32 GB');
        }

        // Must be power of 2 or multiple of 128
        if ($memorySizeMb % 128 !== 0) {
            throw new \InvalidArgumentException('Memory size must be a multiple of 128 MB');
        }
    }

    /**
     * Validate CPU limit
     */
    public function validateCpuLimit(?float $cpuLimit): void
    {
        if ($cpuLimit === null) {
            return; // Optional field
        }

        if ($cpuLimit < 0.1 || $cpuLimit > 16.0) {
            throw new \InvalidArgumentException('CPU limit must be between 0.1 and 16.0');
        }
    }

    /**
     * Validate project slug
     */
    public function validateProjectSlug(string $slug): void
    {
        $slug = trim($slug);

        if (strlen($slug) < 3 || strlen($slug) > 50) {
            throw new \InvalidArgumentException('Project slug must be between 3 and 50 characters');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new \InvalidArgumentException('Project slug can only contain lowercase letters, numbers, and hyphens');
        }

        if (str_starts_with($slug, '-') || str_ends_with($slug, '-')) {
            throw new \InvalidArgumentException('Project slug cannot start or end with a hyphen');
        }

        if (str_contains($slug, '--')) {
            throw new \InvalidArgumentException('Project slug cannot contain consecutive hyphens');
        }
    }

    /**
     * Validate domain name
     */
    public function validateDomainName(string $domain): void
    {
        $domain = trim(strtolower($domain));

        // Basic domain regex
        if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $domain)) {
            throw new \InvalidArgumentException('Invalid domain name format');
        }

        if (strlen($domain) > 253) {
            throw new \InvalidArgumentException('Domain name is too long');
        }

        // Check each label
        $labels = explode('.', $domain);
        foreach ($labels as $label) {
            if (strlen($label) > 63) {
                throw new \InvalidArgumentException('Domain label is too long');
            }
        }
    }

    /**
     * Validate email address
     */
    public function validateEmail(string $email): void
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address format');
        }

        if (strlen($email) > 254) {
            throw new \InvalidArgumentException('Email address is too long');
        }
    }

    /**
     * Validate URL
     */
    public function validateUrl(string $url): void
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        // Check scheme
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('URL must use http or https protocol');
        }
    }

    /**
     * Sanitize container name (removes invalid characters)
     */
    public function sanitizeContainerName(string $name): string
    {
        // Convert to lowercase
        $sanitized = strtolower($name);

        // Only allow alphanumeric, hyphens, and underscores
        $sanitized = preg_replace('/[^a-z0-9_-]/', '-', $sanitized);

        // Remove consecutive hyphens/underscores
        $sanitized = preg_replace('/[-_]+/', '-', $sanitized);

        // Trim hyphens and underscores from start/end
        $sanitized = trim($sanitized, '-_');

        // Ensure minimum length
        if (strlen($sanitized) < 3) {
            throw new \InvalidArgumentException('Container name too short after sanitization');
        }

        // Ensure maximum length
        if (strlen($sanitized) > 50) {
            $sanitized = substr($sanitized, 0, 50);
            $sanitized = rtrim($sanitized, '-_');
        }

        return $sanitized;
    }

    /**
     * Sanitize filename (removes invalid characters and path traversal)
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);

        // Remove any null bytes
        $filename = str_replace("\0", '', $filename);

        // Only allow safe characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Prevent hidden files
        if (str_starts_with($filename, '.')) {
            $filename = '_' . $filename;
        }

        if (strlen($filename) === 0 || strlen($filename) > 255) {
            throw new \InvalidArgumentException('Invalid filename length');
        }

        return $filename;
    }
}
