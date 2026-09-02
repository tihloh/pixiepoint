<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use Throwable;
use PixiePoint\App\Admin\Shared\FeatureController;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $message = '';
        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'identity' => 'trim|required|string|max:160',
                'public_host' => 'trim|null_if_empty|nullable|string|max:255',
                'location' => 'trim|null_if_empty|nullable|string|max:255',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                try {
                    $stmt = $this->db->prepare('INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)');
                    $stmt->execute([$data['name'], $data['identity'], $data['public_host'] ?? null, $data['location'] ?? null, bin2hex(random_bytes(24))]);
                    $id = (int)$this->db->lastInsertId();
                    $this->audit('router.created', 'router', $id, 'MikroTik router was registered.', ['identity' => $data['identity']]);
                    $message = '<div class="alert ok">Router registered.</div>';
                } catch (Throwable) {
                    $message = '<div class="alert">That RouterOS identity is already registered or the router could not be saved.</div>';
                }
            }
        }

        $this->page('Routers', __DIR__ . '/views/index.php', [
            'message' => $message,
            'routers' => $this->db->query('SELECT * FROM routers ORDER BY created_at DESC')->fetchAll(),
            'canManageRouters' => $this->auth->can('routers.manage'),
            'csrf' => csrf_token(),
        ]);
    }
}
