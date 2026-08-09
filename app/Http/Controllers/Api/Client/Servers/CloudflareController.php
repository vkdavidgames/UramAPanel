<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Cloudflare\CloudflareDNSService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class CloudflareController extends ClientApiController
{
    protected CloudflareDNSService $dnsService;

    public function __construct(CloudflareDNSService $dnsService)
    {
        parent::__construct();
        $this->dnsService = $dnsService;
    }

    public function index(Server $server)
    {
        $record = $this->dnsService->getRecordForServer($server);
        return response()->json(['record' => $record]);
    }

    public function updateProxy(Request $request, Server $server)
    {
        $request->validate(['proxied' => 'required|boolean']);
        
        $record = $this->dnsService->updateProxyStatus(
            $server, 
            $request->input('proxied')
        );

        return response()->json(['record' => $record]);
    }
}
