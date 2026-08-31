<?php

declare(strict_types=1);

namespace PixiePoint\App\Api;

use PDO;
use PixiePoint\App\Http\Request;

final class AccountingController
{
    public function __construct(private PDO $db, private array $config) {}

    public function health(Request $request): never
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

    public function accounting(Request $request): never
    {
        header('Content-Type: application/json');
        $provided = preg_replace('/^Bearer\s+/i', '', (string)($request->server['HTTP_AUTHORIZATION'] ?? ''));
        if (!hash_equals((string)($this->config['accounting_key'] ?? ''), $provided)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'unauthorized']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['session_id'])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'session_id required']);
            exit;
        }

        $status = in_array($payload['status'] ?? '', ['start', 'update', 'stop'], true) ? $payload['status'] : 'update';
        $mapped = $status === 'stop' ? 'stopped' : 'active';
        $sessionId = substr((string)$payload['session_id'], 0, 128);
        $username = substr((string)($payload['username'] ?? ''), 0, 128);
        $clientIp = substr((string)($payload['client_ip'] ?? ''), 0, 45);
        $mac = client_mac((string)($payload['mac'] ?? ''));
        $routerIdentity = substr((string)($payload['router_identity'] ?? ''), 0, 128);
        $deviceId = $routerId = $userId = null;

        $this->db->beginTransaction();
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
            $stmt->execute([$userId,$sessionId,$routerId,$deviceId,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,max(0,(int)($payload['uptime']??0)),max(0,(int)($payload['bytes_in']??0)),max(0,(int)($payload['bytes_out']??0)),substr((string)($payload['terminate_cause']??''),0,128),$recordId]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO sessions(user_id,radius_session_id,router_id,device_id,username,client_ip,status,started_at,updated_at,ended_at,uptime_seconds,bytes_in,bytes_out,terminate_cause) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$userId,$sessionId,$routerId,$deviceId,$username,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,max(0,(int)($payload['uptime']??0)),max(0,(int)($payload['bytes_in']??0)),max(0,(int)($payload['bytes_out']??0)),substr((string)($payload['terminate_cause']??''),0,128)]);
        }
        $this->db->commit();
        echo json_encode(['ok' => true]);
        exit;
    }
}
