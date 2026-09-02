<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PDO;

final class AgentController
{
    public function __construct(
        private readonly PDO $db,
        private readonly CommandQueue $queue,
    ) {}

    public function poll(): never
    {
        $router = $this->router();
        $this->touch((int)$router['id']);

        $command = $this->queue->deliverNext((int)$router['id']);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        if ($command === null) exit('');

        echo $command['id'], "\n", $command['command'];
        exit;
    }

    public function ack(): never
    {
        $router = $this->router();
        $this->touch((int)$router['id']);

        $id = max(0, (int)($_GET['id'] ?? 0));
        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $result = trim((string)($_GET['result'] ?? ''));
        if ($id < 1 || !$this->queue->acknowledge((int)$router['id'], $id, $status, $result)) {
            http_response_code(422);
            exit('invalid');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('ok');
    }

    private function router(): array
    {
        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(401);
            exit('unauthorized');
        }
        $stmt = $this->db->prepare('SELECT id,identity FROM routers WHERE api_key=? AND enabled=1 LIMIT 1');
        $stmt->execute([$token]);
        $router = $stmt->fetch();
        if (!$router) {
            http_response_code(401);
            exit('unauthorized');
        }
        return $router;
    }

    private function touch(int $routerId): void
    {
        $stmt = $this->db->prepare('UPDATE routers SET last_seen_at=NOW() WHERE id=?');
        $stmt->execute([$routerId]);
    }
}
