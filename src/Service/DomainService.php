<?php

namespace App\Service;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Server;
use App\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class DomainService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DomainRepository $domainRepository,
        private ServerService $serverService,
        private LoggerInterface $logger,
        private CloudFlareService $cloudFlareService
    ) {
    }

    /**
     * Add a new domain to a project
     */
    public function addDomain(Project $project, string $domainName, bool $isPrimary = false, string $dnsProvider = Domain::DNS_PROVIDER_MANUAL): Domain
    {
        // Check if domain already exists
        $existing = $this->domainRepository->findByDomainName($domainName);
        if ($existing) {
            throw new \RuntimeException('Domain is already registered to another project');
        }

        $domain = new Domain();
        $domain->setProject($project);
        $domain->setDomain($domainName);
        $domain->setIsPrimary($isPrimary);
        $domain->setStatus(Domain::STATUS_PENDING);
        $domain->setDnsProvider($dnsProvider);

        // If this is primary, unset other primaries
        if ($isPrimary) {
            $this->unsetOtherPrimaries($project, $domain);
        }

        // If CloudFlare DNS, create zone and configure DNS automatically
        if ($dnsProvider === Domain::DNS_PROVIDER_PUSHIFY) {
            $this->setupCloudFlareDns($domain, $project);
        }

        $this->entityManager->persist($domain);
        $this->entityManager->flush();

        return $domain;
    }

    /**
     * Setup CloudFlare DNS for a domain
     */
    private function setupCloudFlareDns(Domain $domain, Project $project): void
    {
        try {
            // Create CloudFlare zone
            $result = $this->cloudFlareService->createZone($domain->getDomain());

            if (!$result['success']) {
                $domain->setStatus(Domain::STATUS_FAILED);
                $domain->setLastError('Failed to create CloudFlare zone: ' . $result['error']);
                return;
            }

            $domain->setCloudflareZoneId($result['zone_id']);
            $domain->setCloudflareNameservers($result['nameservers']);

            // Get server IP
            $server = $project->getServer();
            if (!$server) {
                $domain->setStatus(Domain::STATUS_FAILED);
                $domain->setLastError('No server assigned to project');
                return;
            }

            $serverIp = $server->getIpAddress();

            // Create A records (@ and www)
            $this->cloudFlareService->createARecord($result['zone_id'], '@', $serverIp);
            $this->cloudFlareService->createARecord($result['zone_id'], 'www', $serverIp);

            $domain->setStatus(Domain::STATUS_VERIFYING);

            $this->logger->info('CloudFlare DNS configured for domain', [
                'domain' => $domain->getDomain(),
                'zone_id' => $result['zone_id'],
                'server_ip' => $serverIp
            ]);
        } catch (\Exception $e) {
            $domain->setStatus(Domain::STATUS_FAILED);
            $domain->setLastError($e->getMessage());
            $this->logger->error('Failed to setup CloudFlare DNS', [
                'domain' => $domain->getDomain(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Remove a domain
     */
    public function removeDomain(Domain $domain): void
    {
        $project = $domain->getProject();
        $server = $project->getServer();

        // Remove nginx config if exists
        if ($server && $server->isActive()) {
            $this->removeNginxConfig($domain, $server);
        }

        // If CloudFlare managed, delete the zone
        if ($domain->isPushifyDns() && $domain->getCloudflareZoneId()) {
            try {
                $this->cloudFlareService->deleteZone($domain->getCloudflareZoneId());
                $this->logger->info('CloudFlare zone deleted', [
                    'domain' => $domain->getDomain(),
                    'zone_id' => $domain->getCloudflareZoneId()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to delete CloudFlare zone', [
                    'domain' => $domain->getDomain(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->entityManager->remove($domain);
        $this->entityManager->flush();
    }

    /**
     * Verify DNS records for a domain
     */
    public function verifyDns(Domain $domain): bool
    {
        $project = $domain->getProject();
        $server = $project->getServer();

        if (!$server) {
            $domain->setLastError('No server assigned to project');
            $domain->setStatus(Domain::STATUS_FAILED);
            $this->entityManager->flush();
            return false;
        }

        $domain->setStatus(Domain::STATUS_VERIFYING);
        $this->entityManager->flush();

        $domainName = $domain->getDomain();
        $expectedIp = $server->getIpAddress();

        // Check A record
        $aRecords = @dns_get_record($domainName, DNS_A);
        $hasCorrectARecord = false;

        if ($aRecords) {
            foreach ($aRecords as $record) {
                if (isset($record['ip']) && $record['ip'] === $expectedIp) {
                    $hasCorrectARecord = true;
                    break;
                }
            }
        }

        // Also check CNAME (might point to another domain that resolves to our IP)
        if (!$hasCorrectARecord) {
            $resolvedIp = @gethostbyname($domainName);
            if ($resolvedIp === $expectedIp) {
                $hasCorrectARecord = true;
            }
        }

        if ($hasCorrectARecord) {
            $domain->setDnsVerified(true);
            $domain->setDnsVerifiedAt(new \DateTimeImmutable());
            $domain->setStatus(Domain::STATUS_VERIFIED);
            $domain->setLastError(null);
            $this->entityManager->flush();

            $this->logger->info('DNS verified for domain', [
                'domain' => $domainName,
                'ip' => $expectedIp,
            ]);

            return true;
        }

        $domain->setDnsVerified(false);
        $domain->setLastError("DNS not pointing to server. Expected A record: {$expectedIp}");
        $domain->setStatus(Domain::STATUS_PENDING);
        $this->entityManager->flush();

        return false;
    }

    /**
     * Issue SSL certificate using Let's Encrypt (certbot)
     */
    public function issueSslCertificate(Domain $domain): bool
    {
        $project = $domain->getProject();
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            $domain->setLastError('Server not available');
            $this->entityManager->flush();
            return false;
        }

        if (!$domain->isDnsVerified()) {
            $domain->setLastError('DNS must be verified before SSL can be issued');
            $this->entityManager->flush();
            return false;
        }

        $domain->setStatus(Domain::STATUS_SSL_PENDING);
        $this->entityManager->flush();

        try {
            // First, setup nginx config without SSL
            $this->setupNginxConfig($domain, $server, false);

            // Run certbot on the server
            $domainName = $domain->getDomain();
            $certbotCmd = "certbot certonly --nginx -d {$domainName} --non-interactive --agree-tos --email admin@{$domainName} --redirect";

            $result = $this->executeRemoteCommand($server, $certbotCmd);

            if (!$result['success']) {
                // Try webroot method as fallback
                $certbotCmd = "certbot certonly --webroot -w /var/www/html -d {$domainName} --non-interactive --agree-tos --email admin@{$domainName}";
                $result = $this->executeRemoteCommand($server, $certbotCmd);
            }

            if (!$result['success']) {
                throw new \RuntimeException('Certbot failed: ' . $result['error']);
            }

            // Update nginx config with SSL
            $this->setupNginxConfig($domain, $server, true);

            // Reload nginx
            $this->executeRemoteCommand($server, 'nginx -t && systemctl reload nginx');

            $domain->setSslEnabled(true);
            $domain->setSslIssuedAt(new \DateTimeImmutable());
            $domain->setSslExpiresAt(new \DateTimeImmutable('+90 days')); // Let's Encrypt certs are valid for 90 days
            $domain->setStatus(Domain::STATUS_SSL_ACTIVE);
            $domain->setLastError(null);
            $this->entityManager->flush();

            $this->logger->info('SSL certificate issued', ['domain' => $domainName]);

            return true;

        } catch (\Exception $e) {
            $domain->setLastError($e->getMessage());
            $domain->setStatus(Domain::STATUS_FAILED);
            $this->entityManager->flush();

            $this->logger->error('SSL issuance failed', [
                'domain' => $domain->getDomain(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Setup Nginx reverse proxy configuration
     */
    public function setupNginxConfig(Domain $domain, Server $server, bool $withSsl = false): void
    {
        $project = $domain->getProject();
        $domainName = $domain->getDomain();
        $port = $project->getContainerPort() ?? 3000;

        if ($withSsl) {
            $config = $this->generateNginxConfigWithSsl($domainName, $port);
        } else {
            $config = $this->generateNginxConfig($domainName, $port);
        }

        $configPath = "/etc/nginx/sites-available/{$domainName}";
        $enabledPath = "/etc/nginx/sites-enabled/{$domainName}";

        // Write config file
        $escapedConfig = str_replace("'", "'\\''", $config);
        $this->executeRemoteCommand($server, "echo '{$escapedConfig}' > {$configPath}");

        // Enable site
        $this->executeRemoteCommand($server, "ln -sf {$configPath} {$enabledPath}");

        // Test and reload nginx
        $result = $this->executeRemoteCommand($server, 'nginx -t');
        if ($result['success']) {
            $this->executeRemoteCommand($server, 'systemctl reload nginx');
        }
    }

    /**
     * Remove Nginx configuration for a domain
     */
    private function removeNginxConfig(Domain $domain, Server $server): void
    {
        $domainName = $domain->getDomain();
        $this->executeRemoteCommand($server, "rm -f /etc/nginx/sites-enabled/{$domainName}");
        $this->executeRemoteCommand($server, "rm -f /etc/nginx/sites-available/{$domainName}");
        $this->executeRemoteCommand($server, 'systemctl reload nginx');
    }

    /**
     * Generate basic Nginx config (HTTP only)
     */
    private function generateNginxConfig(string $domain, int $port): string
    {
        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400;
    }
}
NGINX;
    }

    /**
     * Generate Nginx config with SSL
     */
    private function generateNginxConfigWithSsl(string $domain, int $port): string
    {
        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domain};

    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security "max-age=63072000" always;

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400;
    }
}
NGINX;
    }

    /**
     * Renew SSL certificates that are expiring soon
     */
    public function renewExpiringCertificates(): int
    {
        $domains = $this->domainRepository->findDomainsNeedingSslRenewal();
        $renewed = 0;

        foreach ($domains as $domain) {
            $server = $domain->getProject()->getServer();
            if (!$server || !$server->isActive()) {
                continue;
            }

            $result = $this->executeRemoteCommand($server, 'certbot renew --quiet');
            if ($result['success']) {
                $domain->setSslExpiresAt(new \DateTimeImmutable('+90 days'));
                $this->entityManager->flush();
                $renewed++;
            }
        }

        return $renewed;
    }

    /**
     * Set domain as primary
     */
    public function setPrimary(Domain $domain): void
    {
        $project = $domain->getProject();
        $this->unsetOtherPrimaries($project, $domain);

        $domain->setIsPrimary(true);
        $this->entityManager->flush();

        // Update project's production URL
        $project->setProductionUrl($domain->getFullUrl());
        $this->entityManager->flush();
    }

    private function unsetOtherPrimaries(Project $project, Domain $exceptDomain): void
    {
        $domains = $this->domainRepository->findByProject($project);
        foreach ($domains as $d) {
            if ($d->getId() !== $exceptDomain->getId()) {
                $d->setIsPrimary(false);
            }
        }
    }

    private function executeRemoteCommand(Server $server, string $command): array
    {
        $keyFile = $this->createTempKeyFile($server);

        $process = new Process([
            'ssh',
            '-i', $keyFile,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=30',
            '-p', (string) $server->getSshPort(),
            $server->getSshUser() . '@' . $server->getIpAddress(),
            $command
        ]);
        $process->setTimeout(120);
        $process->run();

        @unlink($keyFile);

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    private function createTempKeyFile(Server $server): string
    {
        $keyFile = sys_get_temp_dir() . '/pushify_domain_' . uniqid() . '.key';
        file_put_contents($keyFile, $server->getSshPrivateKey());
        chmod($keyFile, 0600);
        return $keyFile;
    }
}
