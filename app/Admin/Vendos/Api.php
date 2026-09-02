<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PDO;

final class Api
{
    public function __construct(private PDO $db) {}

    /** @return array<int,array<string,mixed>> */
    public function forHotspot(string $routerIdentity, string $serverIp = '', string $interface = ''): array
    {
        $sql = 'SELECT v.id,v.name,v.base_url,v.server_ip,v.interface_name,v.password_mode,v.charging_enabled,v.eload_enabled '
            . 'FROM vendos v JOIN routers r ON r.id=v.router_id '
            . 'WHERE v.enabled=1 AND r.enabled=1 AND r.identity=? AND v.server_ip=? ';
        $params = [$routerIdentity, $serverIp];

        if ($interface !== '') {
            $sql .= "AND (v.interface_name IS NULL OR v.interface_name='' OR v.interface_name=?) ";
            $params[] = $interface;
        }

        $sql .= 'ORDER BY v.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $v): array => [
            'id' => (string)$v['id'],
            'name' => $v['name'],
            'baseUrl' => rtrim((string)$v['base_url'], '/'),
            'serverIp' => (string)$v['server_ip'],
            'interfaceName' => $v['interface_name'] ?: '',
            'passwordMode' => $v['password_mode'] ?: 'blank',
            'chargingEnabled' => (bool)$v['charging_enabled'],
            'eloadEnabled' => (bool)$v['eload_enabled'],
        ], $stmt->fetchAll());
    }
}
