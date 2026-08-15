<?php

namespace Pterodactyl\Services\Cloudflare;

use GuzzleHttp\Client;
use Pterodactyl\Models\Server;

class CloudflareDNSService
{
    protected Client $client;
    protected string $zoneId;
    protected string $apiToken;

    public function __construct()
    {
        $this->zoneId = config('services.cloudflare.zone_id', env('CLOUDFLARE_ZONE_ID', ''));
        $this->apiToken = config('services.cloudflare.api_token', env('CLOUDFLARE_API_TOKEN', ''));
        
        $this->client = new Client([
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getRecordForServer(Server $server): ?array
    {
        $allocation = $server->allocation;
        $response = $this->client->get("zones/{$this->zoneId}/dns_records", [
            'query' => ['content' => $allocation->ip],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['result'][0] ?? null;
    }

    public function updateProxyStatus(Server $server, bool $proxied): array
    {
        $record = $this->getRecordForServer($server);
        if (!$record) {
            throw new \Exception('Nem található Cloudflare DNS rekord ehhez a szerverhez.');
        }

        $response = $this->client->patch("zones/{$this->zoneId}/dns_records/{$record['id']}", [
            'json' => [
                'proxied' => $proxied,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['result'];
    }
}
