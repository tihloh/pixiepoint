<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PDO;

final class Api
{
    public function __construct(private PDO $db) {}

    /** @return array<int,array<string,mixed>> */
    public function forHotspot(string $routerIdentity, string $clientIp = '', string $interface = ''): array
    {
        $stmt = $this->db->prepare(
            'SELECT v.id,v.name,v.base_url,v.client_subnet,v.interface_name,v.password_mode,v.charging_enabled,v.eload_enabled '
            . 'FROM vendos v JOIN routers r ON r.id=v.router_id '
            . 'WHERE v.enabled=1 AND r.enabled=1 AND r.identity=? ORDER BY v.name'
        );
        $stmt->execute([$routerIdentity]);
        $rows = $stmt->fetchAll();

        $subnetMatches = array_values(array_filter($rows, fn (array $v): bool =>
            trim((string)($v['client_subnet'] ?? '')) !== '' && $this->ipInCidr($clientIp, (string)$v['client_subnet'])
        ));

        if ($subnetMatches) {
            $rows = $subnetMatches;
        } else {
            $rows = array_values(array_filter($rows, static fn (array $v): bool =>
                trim((string)($v['interface_name'] ?? '')) === '' || (string)$v['interface_name'] === $interface
            ));
        }

        return array_map(static fn (array $v): array => [
            'id' => (string)$v['id'],
            'name' => $v['name'],
            'baseUrl' => rtrim((string)$v['base_url'], '/'),
            'clientSubnet' => $v['client_subnet'] ?: '',
            'interfaceName' => $v['interface_name'] ?: '',
            'passwordMode' => $v['password_mode'] ?: 'blank',
            'chargingEnabled' => (bool)$v['charging_enabled'],
            'eloadEnabled' => (bool)$v['eload_enabled'],
        ], $rows);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($ip === '' || !str_contains($cidr, '/')) return false;
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, '');
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
        $bits = strlen($ipBin) * 8;
        $prefix = filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $bits]]);
        if ($prefix === false) return false;
        $full = intdiv((int)$prefix, 8);
        $remain = (int)$prefix % 8;
        if ($full && substr($ipBin, 0, $full) !== substr($netBin, 0, $full)) return false;
        if (!$remain) return true;
        $mask = (0xFF << (8 - $remain)) & 0xFF;
        return (ord($ipBin[$full]) & $mask) === (ord($netBin[$full]) & $mask);
    }
}
