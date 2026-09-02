<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use Tihloh\Prefab\Input\Input;

final class HotspotController
{
    public function __construct(private Api $api) {}

    public function index(): never
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
        echo json_encode([
            'ok' => true,
            'vendos' => $this->api->forHotspot((string)$data['router_identity'], (string)($data['interface'] ?? '')),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
