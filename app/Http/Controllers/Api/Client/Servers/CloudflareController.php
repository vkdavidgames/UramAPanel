namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Cloudflare\CloudflareDNSService;

class CloudflareController extends ClientApiController
{
    public function index(Server $server)
    {
        // Cloudflare DNS rekordok lekérése a szerverhez
    }

    public function store(Server $server, CloudflareDNSService $service)
    {
        // Új A/AAAA/SRV rekord létrehozása a Cloudflare Zone-ban
    }
}
