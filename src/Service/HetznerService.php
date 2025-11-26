<?php

namespace App\Service;

use App\Entity\Server;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HetznerService
{
    private const API_URL = 'https://api.hetzner.cloud/v1';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private ServerService $serverService,
        private LoggerInterface $logger,
        private string $hetznerApiToken
    ) {
    }

    /**
     * Check if Hetzner is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->hetznerApiToken);
    }

    /**
     * List available server types
     */
    public function getServerTypes(): array
    {
        $response = $this->request('GET', '/server_types');

        return array_map(fn($type) => [
            'id' => $type['id'],
            'name' => $type['name'],
            'description' => $type['description'],
            'cores' => $type['cores'],
            'memory' => $type['memory'],
            'disk' => $type['disk'],
            'prices' => $type['prices'],
        ], $response['server_types'] ?? []);
    }

    /**
     * List available locations/datacenters
     */
    public function getLocations(): array
    {
        $response = $this->request('GET', '/locations');

        return array_map(fn($loc) => [
            'id' => $loc['id'],
            'name' => $loc['name'],
            'description' => $loc['description'],
            'city' => $loc['city'],
            'country' => $loc['country'],
        ], $response['locations'] ?? []);
    }

    /**
     * List available OS images
     */
    public function getImages(): array
    {
        $response = $this->request('GET', '/images?type=system&status=available');

        return array_filter(
            array_map(fn($img) => [
                'id' => $img['id'],
                'name' => $img['name'],
                'description' => $img['description'],
                'os_flavor' => $img['os_flavor'],
                'os_version' => $img['os_version'],
            ], $response['images'] ?? []),
            fn($img) => in_array($img['os_flavor'], ['ubuntu', 'debian'])
        );
    }

    /**
     * Create a new server on Hetzner
     */
    public function createServer(
        User $owner,
        string $name,
        string $serverType = 'cx22',
        string $location = 'nbg1',
        string $image = 'ubuntu-22.04'
    ): Server {
        // Generate SSH key pair
        $keyPair = $this->serverService->generateKeyPair();

        // Create SSH key on Hetzner
        $sshKeyResponse = $this->request('POST', '/ssh_keys', [
            'name' => 'pushify-' . $name . '-' . uniqid(),
            'public_key' => $keyPair['public'],
        ]);

        $sshKeyId = $sshKeyResponse['ssh_key']['id'];

        // Cloud-init script to install Docker, Nginx, and Certbot
        $userData = <<<'CLOUD_INIT'
#cloud-config
package_update: true
package_upgrade: true

packages:
  - docker.io
  - docker-compose
  - nginx
  - certbot
  - python3-certbot-nginx

runcmd:
  - sleep 10
  - systemctl daemon-reload
  - systemctl enable docker
  - systemctl start docker
  - sleep 5
  - systemctl enable nginx
  - systemctl start nginx
  - mkdir -p /etc/nginx/sites-available
  - mkdir -p /etc/nginx/sites-enabled
  - |
    cat > /etc/nginx/nginx.conf << 'EOF'
    user www-data;
    worker_processes auto;
    pid /run/nginx.pid;
    include /etc/nginx/modules-enabled/*.conf;

    events {
        worker_connections 768;
    }

    http {
        sendfile on;
        tcp_nopush on;
        types_hash_max_size 2048;
        include /etc/nginx/mime.types;
        default_type application/octet-stream;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_prefer_server_ciphers on;
        access_log /var/log/nginx/access.log;
        error_log /var/log/nginx/error.log;
        gzip on;
        include /etc/nginx/conf.d/*.conf;
        include /etc/nginx/sites-enabled/*;
    }
    EOF
  - systemctl restart nginx
  - echo "Pushify server setup complete" > /var/log/pushify-setup.log
CLOUD_INIT;

        // Create the server
        $serverResponse = $this->request('POST', '/servers', [
            'name' => 'pushify-' . $name,
            'server_type' => $serverType,
            'location' => $location,
            'image' => $image,
            'ssh_keys' => [$sshKeyId],
            'start_after_create' => true,
            'user_data' => $userData,
            'labels' => [
                'managed_by' => 'pushify',
                'owner' => (string) $owner->getId(),
            ],
        ]);

        $hetznerServer = $serverResponse['server'];

        // Create Server entity
        $server = new Server();
        $server->setOwner($owner);
        $server->setName($name);
        $server->setProvider(Server::PROVIDER_HETZNER);
        $server->setProviderId((string) $hetznerServer['id']);
        $server->setIpAddress($hetznerServer['public_net']['ipv4']['ip']);
        $server->setSshUser('root');
        $server->setSshPrivateKey($keyPair['private']);
        $server->setStatus(Server::STATUS_PENDING);
        $server->setRegion($location);
        $server->setOs($image);

        // Set specs from server type
        $server->setCpuCores($hetznerServer['server_type']['cores']);
        $server->setMemoryMb((int) ($hetznerServer['server_type']['memory'] * 1024));
        $server->setDiskGb($hetznerServer['server_type']['disk']);

        $this->entityManager->persist($server);
        $this->entityManager->flush();

        $this->logger->info('Hetzner server created', [
            'server_id' => $server->getId(),
            'hetzner_id' => $hetznerServer['id'],
            'ip' => $server->getIpAddress(),
        ]);

        return $server;
    }

    /**
     * Delete a server from Hetzner
     */
    public function deleteServer(Server $server): bool
    {
        if ($server->getProvider() !== Server::PROVIDER_HETZNER || !$server->getProviderId()) {
            throw new \RuntimeException('Server is not a Hetzner server');
        }

        try {
            $this->request('DELETE', '/servers/' . $server->getProviderId());

            $this->entityManager->remove($server);
            $this->entityManager->flush();

            $this->logger->info('Hetzner server deleted', [
                'server_id' => $server->getId(),
                'hetzner_id' => $server->getProviderId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete Hetzner server', [
                'server_id' => $server->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get server status from Hetzner
     */
    public function getServerStatus(Server $server): array
    {
        if (!$server->getProviderId()) {
            throw new \RuntimeException('No provider ID set');
        }

        $response = $this->request('GET', '/servers/' . $server->getProviderId());
        $hetznerServer = $response['server'];

        return [
            'status' => $hetznerServer['status'],
            'ip' => $hetznerServer['public_net']['ipv4']['ip'],
            'cores' => $hetznerServer['server_type']['cores'],
            'memory' => $hetznerServer['server_type']['memory'],
            'disk' => $hetznerServer['server_type']['disk'],
        ];
    }

    /**
     * Power actions (on, off, reboot)
     */
    public function powerAction(Server $server, string $action): bool
    {
        if (!in_array($action, ['poweron', 'poweroff', 'reboot'])) {
            throw new \InvalidArgumentException('Invalid power action');
        }

        $this->request('POST', '/servers/' . $server->getProviderId() . '/actions/' . $action);
        return true;
    }

    /**
     * Make API request to Hetzner
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Hetzner API token is not configured');
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->hetznerApiToken,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['json'] = $data;
        }

        $response = $this->httpClient->request($method, self::API_URL . $endpoint, $options);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $error = $response->toArray(false);
            throw new \RuntimeException($error['error']['message'] ?? 'API request failed');
        }

        if ($statusCode === 204) {
            return [];
        }

        return $response->toArray();
    }
}
