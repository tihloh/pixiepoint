<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use Tihloh\Prefab\Input\Input;

final class VendoController
{
    public function __construct(private PDO $db) {}

    public function listForHotspot(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');

        $result = Input::fromRequest()->process([
            'router_identity' => 'trim|required|string|max:160',
            'interface' => 'trim|null_if_empty|nullable|string|max:128',
        ]);

        if ($result->fails()) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'vendos' => []], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $data = $result->validated();
        $sql = 'SELECT v.id,v.name,v.base_url,v.interface_name,v.password_mode,v.charging_enabled,v.eload_enabled '
            . 'FROM vendos v JOIN routers r ON r.id=v.router_id '
            . 'WHERE v.enabled=1 AND r.enabled=1 AND r.identity=? '
            . "AND (v.interface_name IS NULL OR v.interface_name='' OR v.interface_name=?) "
            . 'ORDER BY v.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(string)$data['router_identity'], (string)($data['interface'] ?? '')]);

        $vendos = array_map(static fn (array $v): array => [
            'id' => (string)$v['id'],
            'name' => $v['name'],
            'baseUrl' => rtrim((string)$v['base_url'], '/'),
            'interfaceName' => $v['interface_name'] ?: '',
            'passwordMode' => $v['password_mode'] ?: 'blank',
            'chargingEnabled' => (bool)$v['charging_enabled'],
            'eloadEnabled' => (bool)$v['eload_enabled'],
        ], $stmt->fetchAll());

        echo json_encode(['ok' => true, 'vendos' => $vendos], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
