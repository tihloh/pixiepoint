<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PDO;
use PixiePoint\App\Services\BusinessName;

final class Api
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function forHotspot(string $routerIdentity, string $serverIp = '', string $clientIp = '', string $interface = ''): array
    {
        return $this->match($routerIdentity, $serverIp, $clientIp, $interface)['selected'];
    }

    public function debugForHotspot(string $routerIdentity, string $serverIp = '', string $clientIp = '', string $interface = ''): array
    {
        $result = $this->match($routerIdentity, $serverIp, $clientIp, $interface);
        $normalizedServer = $this->normalizeServerAddress($serverIp);
        $targets = array_values(array_filter($result['candidates'], static fn (array $v): bool => (bool) ($v['debug_enabled'] ?? false)));

        return [
            'input' => ['routerIdentity' => $routerIdentity,'serverAddress' => $serverIp,'normalizedServerIp' => $normalizedServer,'clientIp' => $clientIp,'interface' => $interface],
            'candidateCount' => count($result['candidates']),'selectedCount' => count($result['selected']),'selectedIds' => array_column($result['selected'], 'id'),
            'debugTargetIds' => array_map(static fn (array $v): string => (string) $v['id'], $targets),
            'candidates' => array_map(function (array $v) use ($normalizedServer, $clientIp, $interface): array {
                return[
                    'id' => (string) $v['id'],'name' => $v['name'],'businessName' => $v['business_name'],'debugEnabled' => (bool) ($v['debug_enabled'] ?? false),'serverIp' => (string) ($v['server_ip'] ?? ''),'clientSubnet' => (string) ($v['client_subnet'] ?? ''),'interface' => (string) ($v['interface_name'] ?? ''),'baseUrl' => rtrim((string) $v['base_url'], '/'),
                    'serverMatch' => $normalizedServer !== '' && (string) ($v['server_ip'] ?? '') === $normalizedServer,
                    'subnetMatch' => $clientIp !== '' && trim((string) ($v['client_subnet'] ?? '')) !== '' && $this->ipInCidr($clientIp, (string) $v['client_subnet']),
                    'interfaceMatch' => $interface !== '' && (string) ($v['interface_name'] ?? '') === $interface,
                ];
            }, $targets),
        ];
    }

    public function hasDebugTarget(string $routerIdentity): bool
    {
        if ($routerIdentity === '') {
            return false;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM vendos v JOIN routers r ON r.id=v.router_id WHERE r.identity=? AND r.enabled=1 AND v.debug_enabled=1 LIMIT 1');
        $stmt->execute([$routerIdentity]);

        return (bool) $stmt->fetchColumn();
    }

    private function match(string $routerIdentity, string $serverIp, string $clientIp, string $interface): array
    {
        $stmt = $this->db->prepare('SELECT v.id,v.name,v.base_url,v.server_ip,v.client_subnet,v.interface_name,v.password_mode,v.charging_enabled,v.eload_enabled,v.debug_enabled FROM vendos v JOIN routers r ON r.id=v.router_id WHERE v.enabled=1 AND r.enabled=1 AND r.identity=? ORDER BY v.name');
        $stmt->execute([$routerIdentity]);
        $candidates = $stmt->fetchAll();
        $businessNames = new BusinessName($this->db);
        foreach ($candidates as &$vendo) {
            $vendo['business_name'] = $businessNames->vendo((int) $vendo['id']);
        }
        unset($vendo);

        $rows = $candidates;
        $serverIp = $this->normalizeServerAddress($serverIp);
        if ($serverIp !== '') {
            $matches = array_values(array_filter($rows, static fn (array $v): bool => (string) ($v['server_ip'] ?? '') === $serverIp));
            if ($matches) {
                $rows = $matches;
            }
        }
        if (count($rows) > 1 && $clientIp !== '') {
            $matches = array_values(array_filter($rows, fn (array $v): bool => trim((string) ($v['client_subnet'] ?? '')) !== '' && $this->ipInCidr($clientIp, (string) $v['client_subnet'])));
            if ($matches) {
                $rows = $matches;
            }
        }
        if (count($rows) > 1 && $interface !== '') {
            $matches = array_values(array_filter($rows, static fn (array $v): bool => (string) ($v['interface_name'] ?? '') === $interface));
            if ($matches) {
                $rows = $matches;
            }
        }

        $selected = array_map(static fn (array $v): array => [
            'id' => (string) $v['id'],
            'name' => $v['name'],
            'businessName' => $v['business_name'],
            'baseUrl' => rtrim((string) $v['base_url'], '/'),
            'serverIp' => (string) ($v['server_ip'] ?? ''),
            'clientSubnet' => (string) ($v['client_subnet'] ?? ''),
            'interfaceName' => (string) ($v['interface_name'] ?? ''),
            'passwordMode' => $v['password_mode'] ?: 'blank',
            'chargingEnabled' => (bool) $v['charging_enabled'],
            'eloadEnabled' => (bool) $v['eload_enabled'],
        ], $rows);

        return ['candidates' => $candidates,'selected' => $selected];
    }

    private function normalizeServerAddress(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return $value;
        }
        if (preg_match('/^\[([^]]+)\](?::\d+)?$/', $value, $m) && filter_var($m[1], FILTER_VALIDATE_IP) !== false) {
            return $m[1];
        }
        if (preg_match('/^([^:]+):(\d+)$/', $value, $m) && filter_var($m[1], FILTER_VALIDATE_IP) !== false) {
            return $m[1];
        }

        return $value;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if ($ip === '' || !str_contains($cidr, '/')) {
            return false;
        }[$network,$prefix] = array_pad(explode('/', $cidr, 2), 2, '');
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }$bits = strlen($ipBin) * 8;
        $prefix = filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0,'max_range' => $bits]]);
        if ($prefix === false) {
            return false;
        }$full = intdiv((int) $prefix, 8);
        $remain = (int) $prefix % 8;
        if ($full && substr($ipBin, 0, $full) !== substr($netBin, 0, $full)) {
            return false;
        }if (!$remain) {
            return true;
        }$mask = (0xFF << (8 - $remain)) & 0xFF;

        return(ord($ipBin[$full]) & $mask) === (ord($netBin[$full]) & $mask);
    }
}
