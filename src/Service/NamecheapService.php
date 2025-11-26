<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NamecheapService
{
    private const SANDBOX_URL = 'https://api.sandbox.namecheap.com/xml.response';
    private const PRODUCTION_URL = 'https://api.namecheap.com/xml.response';

    private string $apiUser;
    private string $apiKey;
    private string $username;
    private string $clientIp;
    private bool $sandbox;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $namecheapApiUser = '',
        string $namecheapApiKey = '',
        string $namecheapUsername = '',
        string $namecheapClientIp = '',
        bool $namecheapSandbox = true
    ) {
        $this->apiUser = $namecheapApiUser ?: ($_ENV['NAMECHEAP_API_USER'] ?? '');
        $this->apiKey = $namecheapApiKey ?: ($_ENV['NAMECHEAP_API_KEY'] ?? '');
        $this->username = $namecheapUsername ?: ($_ENV['NAMECHEAP_USERNAME'] ?? '');
        $this->clientIp = $namecheapClientIp ?: ($_ENV['NAMECHEAP_CLIENT_IP'] ?? $_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
        $this->sandbox = $namecheapSandbox || (($_ENV['NAMECHEAP_SANDBOX'] ?? 'true') === 'true');
    }

    /**
     * Check if Namecheap API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUser) && !empty($this->apiKey) && !empty($this->username);
    }

    /**
     * Search for domain availability
     */
    public function checkDomainAvailability(string $domain): array
    {
        $response = $this->request('namecheap.domains.check', [
            'DomainList' => $domain,
        ]);

        $results = [];
        if (isset($response['DomainCheckResult'])) {
            $domainResults = $response['DomainCheckResult'];
            // Handle single result vs multiple
            if (isset($domainResults['@attributes'])) {
                $domainResults = [$domainResults];
            }

            foreach ($domainResults as $result) {
                $attrs = $result['@attributes'] ?? $result;
                $results[] = [
                    'domain' => $attrs['Domain'] ?? '',
                    'available' => ($attrs['Available'] ?? 'false') === 'true',
                    'premium' => ($attrs['IsPremiumName'] ?? 'false') === 'true',
                    'premiumPrice' => $attrs['PremiumRegistrationPrice'] ?? null,
                ];
            }
        }

        return $results;
    }

    /**
     * Check multiple domains at once
     */
    public function checkDomainsAvailability(array $domains): array
    {
        $response = $this->request('namecheap.domains.check', [
            'DomainList' => implode(',', $domains),
        ]);

        $results = [];
        if (isset($response['DomainCheckResult'])) {
            $domainResults = $response['DomainCheckResult'];
            if (isset($domainResults['@attributes'])) {
                $domainResults = [$domainResults];
            }

            foreach ($domainResults as $result) {
                $attrs = $result['@attributes'] ?? $result;
                $results[] = [
                    'domain' => $attrs['Domain'] ?? '',
                    'available' => ($attrs['Available'] ?? 'false') === 'true',
                    'premium' => ($attrs['IsPremiumName'] ?? 'false') === 'true',
                    'premiumPrice' => $attrs['PremiumRegistrationPrice'] ?? null,
                ];
            }
        }

        return $results;
    }

    /**
     * Get domain suggestions based on keyword
     */
    public function getDomainSuggestions(string $keyword): array
    {
        // Generate common TLD variations
        $tlds = ['com', 'net', 'org', 'io', 'co', 'dev', 'app', 'xyz', 'tech', 'online'];
        $domains = [];

        foreach ($tlds as $tld) {
            $domains[] = $keyword . '.' . $tld;
        }

        return $this->checkDomainsAvailability($domains);
    }

    /**
     * Get pricing for domain TLDs
     */
    public function getPricing(string $type = 'DOMAIN', string $productCategory = 'DOMAINS'): array
    {
        $response = $this->request('namecheap.users.getPricing', [
            'ProductType' => $type,
            'ProductCategory' => $productCategory,
        ]);

        $pricing = [];
        if (isset($response['ProductType']['ProductCategory']['Product'])) {
            $products = $response['ProductType']['ProductCategory']['Product'];
            if (isset($products['@attributes'])) {
                $products = [$products];
            }

            foreach ($products as $product) {
                $name = $product['@attributes']['Name'] ?? '';
                $prices = [];

                if (isset($product['Price'])) {
                    $priceList = $product['Price'];
                    if (isset($priceList['@attributes'])) {
                        $priceList = [$priceList];
                    }

                    foreach ($priceList as $price) {
                        $attrs = $price['@attributes'] ?? $price;
                        $prices[] = [
                            'duration' => $attrs['Duration'] ?? 1,
                            'type' => $attrs['DurationType'] ?? 'YEAR',
                            'price' => (float) ($attrs['Price'] ?? 0),
                            'currency' => $attrs['Currency'] ?? 'USD',
                        ];
                    }
                }

                $pricing[$name] = $prices;
            }
        }

        return $pricing;
    }

    /**
     * Get pricing for a specific TLD
     */
    public function getTldPricing(string $tld): ?array
    {
        $pricing = $this->getPricing();
        return $pricing[$tld] ?? null;
    }

    /**
     * Register a new domain
     */
    public function registerDomain(
        string $domain,
        int $years,
        array $registrantInfo,
        array $nameservers = []
    ): array {
        // Parse domain
        $parts = explode('.', $domain, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        $sld = $parts[0]; // Second level domain
        $tld = $parts[1]; // Top level domain

        $params = [
            'DomainName' => $domain,
            'Years' => $years,
            // Registrant info
            'RegistrantFirstName' => $registrantInfo['firstName'],
            'RegistrantLastName' => $registrantInfo['lastName'],
            'RegistrantAddress1' => $registrantInfo['address1'],
            'RegistrantCity' => $registrantInfo['city'],
            'RegistrantStateProvince' => $registrantInfo['state'],
            'RegistrantPostalCode' => $registrantInfo['postalCode'],
            'RegistrantCountry' => $registrantInfo['country'],
            'RegistrantPhone' => $registrantInfo['phone'],
            'RegistrantEmailAddress' => $registrantInfo['email'],
            // Tech contact (same as registrant)
            'TechFirstName' => $registrantInfo['firstName'],
            'TechLastName' => $registrantInfo['lastName'],
            'TechAddress1' => $registrantInfo['address1'],
            'TechCity' => $registrantInfo['city'],
            'TechStateProvince' => $registrantInfo['state'],
            'TechPostalCode' => $registrantInfo['postalCode'],
            'TechCountry' => $registrantInfo['country'],
            'TechPhone' => $registrantInfo['phone'],
            'TechEmailAddress' => $registrantInfo['email'],
            // Admin contact (same as registrant)
            'AdminFirstName' => $registrantInfo['firstName'],
            'AdminLastName' => $registrantInfo['lastName'],
            'AdminAddress1' => $registrantInfo['address1'],
            'AdminCity' => $registrantInfo['city'],
            'AdminStateProvince' => $registrantInfo['state'],
            'AdminPostalCode' => $registrantInfo['postalCode'],
            'AdminCountry' => $registrantInfo['country'],
            'AdminPhone' => $registrantInfo['phone'],
            'AdminEmailAddress' => $registrantInfo['email'],
            // Billing contact (same as registrant)
            'AuxBillingFirstName' => $registrantInfo['firstName'],
            'AuxBillingLastName' => $registrantInfo['lastName'],
            'AuxBillingAddress1' => $registrantInfo['address1'],
            'AuxBillingCity' => $registrantInfo['city'],
            'AuxBillingStateProvince' => $registrantInfo['state'],
            'AuxBillingPostalCode' => $registrantInfo['postalCode'],
            'AuxBillingCountry' => $registrantInfo['country'],
            'AuxBillingPhone' => $registrantInfo['phone'],
            'AuxBillingEmailAddress' => $registrantInfo['email'],
        ];

        // Add nameservers if provided
        if (!empty($nameservers)) {
            $params['Nameservers'] = implode(',', $nameservers);
        }

        // Enable WhoisGuard if available
        $params['AddFreeWhoisguard'] = 'yes';
        $params['WGEnabled'] = 'yes';

        $response = $this->request('namecheap.domains.create', $params);

        if (isset($response['DomainCreateResult'])) {
            $result = $response['DomainCreateResult']['@attributes'] ?? $response['DomainCreateResult'];
            return [
                'success' => ($result['Registered'] ?? 'false') === 'true',
                'domain' => $result['Domain'] ?? $domain,
                'domainId' => $result['DomainID'] ?? null,
                'orderId' => $result['OrderID'] ?? null,
                'transactionId' => $result['TransactionID'] ?? null,
                'chargedAmount' => (float) ($result['ChargedAmount'] ?? 0),
                'whoisguard' => ($result['WhoisguardEnable'] ?? 'false') === 'true',
            ];
        }

        return [
            'success' => false,
            'error' => 'Unknown error occurred',
        ];
    }

    /**
     * Get list of domains in account
     */
    public function getDomainList(int $page = 1, int $pageSize = 20): array
    {
        $response = $this->request('namecheap.domains.getList', [
            'Page' => $page,
            'PageSize' => $pageSize,
        ]);

        $domains = [];
        if (isset($response['DomainGetListResult']['Domain'])) {
            $domainList = $response['DomainGetListResult']['Domain'];
            if (isset($domainList['@attributes'])) {
                $domainList = [$domainList];
            }

            foreach ($domainList as $domain) {
                $attrs = $domain['@attributes'] ?? $domain;
                $domains[] = [
                    'id' => $attrs['ID'] ?? '',
                    'name' => $attrs['Name'] ?? '',
                    'user' => $attrs['User'] ?? '',
                    'created' => $attrs['Created'] ?? '',
                    'expires' => $attrs['Expires'] ?? '',
                    'isExpired' => ($attrs['IsExpired'] ?? 'false') === 'true',
                    'isLocked' => ($attrs['IsLocked'] ?? 'false') === 'true',
                    'autoRenew' => ($attrs['AutoRenew'] ?? 'false') === 'true',
                    'whoisGuard' => $attrs['WhoisGuard'] ?? 'NOTPRESENT',
                ];
            }
        }

        $paging = $response['Paging'] ?? [];
        return [
            'domains' => $domains,
            'totalItems' => (int) ($paging['TotalItems'] ?? count($domains)),
            'currentPage' => (int) ($paging['CurrentPage'] ?? $page),
            'pageSize' => (int) ($paging['PageSize'] ?? $pageSize),
        ];
    }

    /**
     * Get domain info
     */
    public function getDomainInfo(string $domain): array
    {
        $response = $this->request('namecheap.domains.getInfo', [
            'DomainName' => $domain,
        ]);

        if (isset($response['DomainGetInfoResult'])) {
            $result = $response['DomainGetInfoResult'];
            $attrs = $result['@attributes'] ?? [];

            return [
                'domain' => $attrs['DomainName'] ?? $domain,
                'status' => $attrs['Status'] ?? 'Unknown',
                'createdDate' => $result['DomainDetails']['CreatedDate'] ?? null,
                'expiredDate' => $result['DomainDetails']['ExpiredDate'] ?? null,
                'nameservers' => $this->parseNameservers($result['DnsDetails'] ?? []),
                'locked' => ($attrs['IsLocked'] ?? 'false') === 'true',
            ];
        }

        return [];
    }

    /**
     * Set DNS host records
     */
    public function setDnsRecords(string $domain, array $records): bool
    {
        $parts = explode('.', $domain, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        $sld = $parts[0];
        $tld = $parts[1];

        $params = [
            'SLD' => $sld,
            'TLD' => $tld,
        ];

        // Add records
        foreach ($records as $index => $record) {
            $i = $index + 1;
            $params["HostName{$i}"] = $record['name'] ?? '@';
            $params["RecordType{$i}"] = $record['type'] ?? 'A';
            $params["Address{$i}"] = $record['value'] ?? '';
            $params["TTL{$i}"] = $record['ttl'] ?? 1800;

            if (isset($record['mxPref'])) {
                $params["MXPref{$i}"] = $record['mxPref'];
            }
        }

        $response = $this->request('namecheap.domains.dns.setHosts', $params);

        return isset($response['DomainDNSSetHostsResult']) &&
               ($response['DomainDNSSetHostsResult']['@attributes']['IsSuccess'] ?? 'false') === 'true';
    }

    /**
     * Get DNS host records
     */
    public function getDnsRecords(string $domain): array
    {
        $parts = explode('.', $domain, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        $response = $this->request('namecheap.domains.dns.getHosts', [
            'SLD' => $parts[0],
            'TLD' => $parts[1],
        ]);

        $records = [];
        if (isset($response['DomainDNSGetHostsResult']['host'])) {
            $hosts = $response['DomainDNSGetHostsResult']['host'];
            if (isset($hosts['@attributes'])) {
                $hosts = [$hosts];
            }

            foreach ($hosts as $host) {
                $attrs = $host['@attributes'] ?? $host;
                $records[] = [
                    'id' => $attrs['HostId'] ?? '',
                    'name' => $attrs['Name'] ?? '',
                    'type' => $attrs['Type'] ?? '',
                    'value' => $attrs['Address'] ?? '',
                    'ttl' => (int) ($attrs['TTL'] ?? 1800),
                    'mxPref' => $attrs['MXPref'] ?? null,
                ];
            }
        }

        return $records;
    }

    /**
     * Set custom nameservers for domain
     */
    public function setNameservers(string $domain, array $nameservers): bool
    {
        $parts = explode('.', $domain, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        $response = $this->request('namecheap.domains.dns.setCustom', [
            'SLD' => $parts[0],
            'TLD' => $parts[1],
            'Nameservers' => implode(',', $nameservers),
        ]);

        return isset($response['DomainDNSSetCustomResult']) &&
               ($response['DomainDNSSetCustomResult']['@attributes']['Update'] ?? 'false') === 'true';
    }

    /**
     * Get account balance
     */
    public function getAccountBalance(): array
    {
        $response = $this->request('namecheap.users.getBalances');

        if (isset($response['UserGetBalancesResult'])) {
            $result = $response['UserGetBalancesResult']['@attributes'] ?? $response['UserGetBalancesResult'];
            return [
                'currency' => $result['Currency'] ?? 'USD',
                'availableBalance' => (float) ($result['AvailableBalance'] ?? 0),
                'accountBalance' => (float) ($result['AccountBalance'] ?? 0),
                'earnedAmount' => (float) ($result['EarnedAmount'] ?? 0),
            ];
        }

        return [
            'currency' => 'USD',
            'availableBalance' => 0,
            'accountBalance' => 0,
            'earnedAmount' => 0,
        ];
    }

    /**
     * Make API request
     */
    private function request(string $command, array $params = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Namecheap API is not configured');
        }

        $url = $this->sandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;

        $queryParams = array_merge([
            'ApiUser' => $this->apiUser,
            'ApiKey' => $this->apiKey,
            'UserName' => $this->username,
            'ClientIp' => $this->clientIp,
            'Command' => $command,
        ], $params);

        $this->logger->debug('Namecheap API request', [
            'command' => $command,
            'sandbox' => $this->sandbox,
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $queryParams,
                'timeout' => 10, // 10 second timeout
            ]);

            $xml = $response->getContent();
            $data = $this->parseXml($xml);

            // Check for errors
            if (isset($data['Errors']['Error'])) {
                $error = $data['Errors']['Error'];
                $errorMsg = is_array($error) ? ($error[0] ?? 'Unknown error') : $error;
                throw new \RuntimeException('Namecheap API error: ' . $errorMsg);
            }

            return $data['CommandResponse'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Namecheap API error', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse XML response to array
     */
    private function parseXml(string $xml): array
    {
        $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($xml);
        return json_decode($json, true);
    }

    /**
     * Parse nameservers from DNS details
     */
    private function parseNameservers(array $dnsDetails): array
    {
        $nameservers = [];
        if (isset($dnsDetails['Nameserver'])) {
            $ns = $dnsDetails['Nameserver'];
            if (is_string($ns)) {
                $nameservers[] = $ns;
            } elseif (is_array($ns)) {
                $nameservers = $ns;
            }
        }
        return $nameservers;
    }
}
