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

    public function install(string $token): never
    {
        $token = trim($token);
        $router = $this->router($token);
        $identity = str_replace(['\r','\n','"'], '', (string)$router['identity']);

        $script = <<<'ROS'
# PixiePoint Router Agent
# Generated for RouterOS by PixiePoint.

:local token "__TOKEN__"
:local baseUrl "https://hs.portalx.win"
:local scriptName "__pixiepoint_cmd"
:local pollUrl ($baseUrl . "/api/router/poll/" . $token)

:do {
    :local fetchResult [/tool fetch url=$pollUrl mode=https output=user as-value]
    :local data ($fetchResult->"data")

    :if ([:len $data] > 0) do={
        :local lineBreak [:find $data "\n"]
        :if ($lineBreak != nil) do={
            :local commandId [:pick $data 0 $lineBreak]
            :local command [:pick $data ($lineBreak + 1) [:len $data]]
            :local ackBase ($baseUrl . "/api/router/ack/" . $token . "/" . $commandId . "/")

            /system script remove [find name=$scriptName]
            /system script add name=$scriptName source=$command

            :do {
                /system script run $scriptName
                /tool fetch url=($ackBase . "completed") mode=https output=none
                :log info ("PixiePoint command " . $commandId . " completed")
            } on-error={
                /tool fetch url=($ackBase . "failed") mode=https output=none
                :log warning ("PixiePoint command " . $commandId . " failed")
            }

            /system script remove [find name=$scriptName]
        }
    }
} on-error={
    :log warning "PixiePoint agent poll failed"
}
ROS;

        $script = str_replace('__TOKEN__', $token, $script);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="PixiePointAgent.rsc"');
        header('Cache-Control: no-store');
        header('X-PixiePoint-Router: ' . $identity);
        echo $script, "\n";
        exit;
    }

    public function poll(string $token): never
    {
        $router = $this->router(trim($token));
        $this->touch((int)$router['id']);

        $command = $this->queue->deliverNext((int)$router['id']);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        if ($command === null) exit('');

        echo $command['id'], "\n", $command['command'];
        exit;
    }

    public function ack(string $token, string|int $id, string $status): never
    {
        $router = $this->router(trim($token));
        $this->touch((int)$router['id']);

        $commandId = max(0, (int)$id);
        $status = strtolower(trim($status));
        if ($commandId < 1 || !$this->queue->acknowledge((int)$router['id'], $commandId, $status, '')) {
            http_response_code(422);
            exit('invalid');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('ok');
    }

    public function test(string|int $id): never
    {
        require_csrf();
        $routerId = max(0, (int)$id);
        $stmt = $this->db->prepare('SELECT id,name,enabled FROM routers WHERE id=? LIMIT 1');
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();

        if (!$router) {
            $_SESSION['admin_flash'] = '<div class="alert">Router not found.</div>';
            redirect('/admin/routers');
        }
        if (!(int)$router['enabled']) {
            $_SESSION['admin_flash'] = '<div class="alert">Enable this router before sending a test command.</div>';
            redirect('/admin/routers');
        }

        $commandId = $this->queue->enqueue($routerId, ':log info "PixiePoint command queue test"', 100);
        $_SESSION['admin_flash'] = '<div class="alert ok">Test command #' . $commandId . ' queued for ' . htmlspecialchars((string)$router['name'], ENT_QUOTES, 'UTF-8') . '.</div>';
        redirect('/admin/routers');
    }

    private function router(string $token): array
    {
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
