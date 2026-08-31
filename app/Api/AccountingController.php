<?php

declare(strict_types=1);

namespace PixiePoint\App\Api;

use PDO;
use InvalidArgumentException;
use Tihloh\Prefab\Input\Input;

final class AccountingController
{
    public function __construct(private PDO $db, private array $config) {}

    public function health(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        try {
            $this->db->query('SELECT 1')->fetchColumn();
            echo json_encode(['ready' => true, 'service' => 'pixiepoint', 'time' => gmdate(DATE_ATOM)]);
        } catch (\Throwable) {
            http_response_code(503);
            echo json_encode(['ready' => false]);
        }
        exit;
    }

    public function accounting(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $provided = preg_replace('/^Bearer\s+/i', '', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (!hash_equals((string)($this->config['accounting_key'] ?? ''), $provided)) {
            $this->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        try {
            $result = Input::fromRequest()->process([
                'session_id' => 'trim|required|string|max:128',
                'status' => 'trim|lowercase|default:update|string|in:start,update,stop',
                'username' => 'trim|null_if_empty|nullable|string|max:128',
                'client_ip' => 'trim|null_if_empty|nullable|string|max:45',
                'mac' => 'trim|null_if_empty|nullable|string|max:64',
                'router_identity' => 'trim|null_if_empty|nullable|string|max:160',
                'uptime' => 'default:0|integer|min:0',
                'bytes_in' => 'default:0|integer|min:0',
                'bytes_out' => 'default:0|integer|min:0',
                'terminate_cause' => 'trim|null_if_empty|nullable|string|max:128',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if ($result->fails()) {
            $this->json(['ok' => false, 'error' => 'invalid_payload', 'fields' => $result->errors()], 422);
        }

        $payload = $result->validated();
        $status = (string)$payload['status'];
        $mapped = $status === 'stop' ? 'stopped' : 'active';
        $sessionId = (string)$payload['session_id'];
        $username = (string)($payload['username'] ?? '');
        $clientIp = (string)($payload['client_ip'] ?? '');
        $mac = client_mac((string)($payload['mac'] ?? ''));
        $routerIdentity = (string)($payload['router_identity'] ?? '');
        $deviceId = $routerId = $userId = null;

        $this->db->beginTransaction();
        try {
            if ($mac !== '') {
                $this->db->prepare('INSERT INTO devices(mac,last_ip,last_seen_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE last_ip=VALUES(last_ip),last_seen_at=VALUES(last_seen_at)')->execute([$mac, $clientIp, now()]);
                $lookup = $this->db->prepare('SELECT id,user_id FROM devices WHERE mac=?');
                $lookup->execute([$mac]);
                $device = $lookup->fetch() ?: [];
                $deviceId = $device['id'] ?? null;
                $userId = $device['user_id'] ?? null;
            }

            if ($routerIdentity !== '') {
                $lookup = $this->db->prepare('SELECT id FROM routers WHERE identity=?');
                $lookup->execute([$routerIdentity]);
                $routerId = $lookup->fetchColumn() ?: null;
                if ($routerId) $this->db->prepare('UPDATE routers SET last_seen_at=? WHERE id=?')->execute([now(), $routerId]);
            }

            $existing = $this->db->prepare('SELECT id FROM sessions WHERE radius_session_id=?');
            $existing->execute([$sessionId]);
            $recordId = $existing->fetchColumn();

            if (!$recordId && $status === 'start') {
                $candidate = $this->db->prepare("SELECT id FROM sessions WHERE username=? AND status='authorizing' ORDER BY id DESC LIMIT 1");
                $candidate->execute([$username]);
                $recordId = $candidate->fetchColumn();
            }

            if ($recordId) {
                $stmt = $this->db->prepare('UPDATE sessions SET user_id=COALESCE(user_id,?),radius_session_id=?,router_id=COALESCE(?,router_id),device_id=COALESCE(?,device_id),client_ip=?,status=?,started_at=COALESCE(started_at,?),updated_at=?,ended_at=?,uptime_seconds=?,bytes_in=?,bytes_out=?,terminate_cause=? WHERE id=?');
                $stmt->execute([$userId,$sessionId,$routerId,$deviceId,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,(int)$payload['uptime'],(int)$payload['bytes_in'],(int)$payload['bytes_out'],(string)($payload['terminate_cause'] ?? ''),$recordId]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO sessions(user_id,radius_session_id,router_id,device_id,username,client_ip,status,started_at,updated_at,ended_at,uptime_seconds,bytes_in,bytes_out,terminate_cause) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$userId,$sessionId,$routerId,$deviceId,$username,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,(int)$payload['uptime'],(int)$payload['bytes_in'],(int)$payload['bytes_out'],(string)($payload['terminate_cause'] ?? '')]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->json(['ok' => false, 'error' => 'accounting_update_failed'], 500);
        }

        $this->json(['ok' => true]);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
