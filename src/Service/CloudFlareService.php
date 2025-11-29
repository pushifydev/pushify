<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CloudFlareService
{
    private const API_BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $cloudflareApiToken,
        private string $cloudflareAccountId
    ) {
    }

    /**
     * Create a new zone (domain) in CloudFlare
     */
    public function createZone(string $domain): array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_BASE_URL . '/zones', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cloudflareApiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'account' => ['id' => $this->cloudflareAccountId],
                    'name' => $domain,
                    'type' => 'full',
                ],
            ]);

            $data = $response->toArray();

            if (!$data['success']) {
                throw new \RuntimeException('CloudFlare API error: ' . json_encode($data['errors']));
            }

            $this->logger->info('CloudFlare zone created', [
                'domain' => $domain,
                'zone_id' => $data['result']['id']
            ]);

            return [
                'success' => true,
                'zone_id' => $data['result']['id'],
                'nameservers' => $data['result']['name_servers'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create CloudFlare zone', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create DNS A record
     */
    public function createARecord(string $zoneId, string $name, string $ip): array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_BASE_URL . "/zones/{$zoneId}/dns_records", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cloudflareApiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => 'A',
                    'name' => $name,
                    'content' => $ip,
                    'ttl' => 1, // Auto
                    'proxied' => false,
                ],
            ]);

            $data = $response->toArray();

            if (!$data['success']) {
                throw new \RuntimeException('CloudFlare API error: ' . json_encode($data['errors']));
            }

            $this->logger->info('CloudFlare A record created', [
                'zone_id' => $zoneId,
                'name' => $name,
                'ip' => $ip
            ]);

            return [
                'success' => true,
                'record_id' => $data['result']['id'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create CloudFlare A record', [
                'zone_id' => $zoneId,
                'name' => $name,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a zone
     */
    public function deleteZone(string $zoneId): array
    {
        try {
            $response = $this->httpClient->request('DELETE', self::API_BASE_URL . "/zones/{$zoneId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cloudflareApiToken,
                ],
            ]);

            $data = $response->toArray();

            if (!$data['success']) {
                throw new \RuntimeException('CloudFlare API error: ' . json_encode($data['errors']));
            }

            $this->logger->info('CloudFlare zone deleted', [
                'zone_id' => $zoneId
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete CloudFlare zone', [
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify nameservers are set correctly
     */
    public function verifyNameservers(string $zoneId): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . "/zones/{$zoneId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cloudflareApiToken,
                ],
            ]);

            $data = $response->toArray();

            if (!$data['success']) {
                throw new \RuntimeException('CloudFlare API error: ' . json_encode($data['errors']));
            }

            $status = $data['result']['status'];
            $nameservers = $data['result']['name_servers'];

            return [
                'success' => true,
                'active' => $status === 'active',
                'status' => $status,
                'nameservers' => $nameservers,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify CloudFlare nameservers', [
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
