<?php

declare(strict_types=1);

namespace PixiePoint\App\Api;

use PDO;
use InvalidArgumentException;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use Tihloh\Prefab\Input\Input;

final class AccountingController
{
    public function __construct(
        private PDO $db,
        private array $config,
        private NetworkDeviceIdentity $devices,
    ) {}

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
                $scope = 'router:' . ($routerIdentity !== '' ? $routerIdentity : 'unknown');
                $device = $this->devices->resolve($mac, $scope, $clientIp) ?: [];
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

    public function loginEvent(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $result = Input::fromRequest()->process([
                'event_key' => 'trim|required|string|max:191',
                'router_identity' => 'trim|required|string|max:160',
                'username' => 'trim|required|string|max:128',
                'mac' => 'trim|null_if_empty|nullable|string|max:64',
                'client_ip' => 'trim|null_if_empty|nullable|string|max:45',
                'interface_name' => 'trim|null_if_empty|nullable|string|max:128',
                'device_name' => 'trim|null_if_empty|nullable|string|max:255',
                'vendo_name' => 'trim|null_if_empty|nullable|string|max:255',
                'amount_pesos' => 'default:0|integer|min:0|max:1000000',
                'duration_seconds' => 'default:0|integer|min:0',
                'is_extension' => 'default:0|integer|min:0|max:1',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if ($result->fails()) {
            $this->json(['ok' => false, 'error' => 'invalid_payload', 'fields' => $result->errors()], 422);
        }

        $payload = $result->validated();
        $routerStmt = $this->db->prepare('SELECT * FROM routers WHERE identity=? AND enabled=1 LIMIT 1');
        $routerStmt->execute([(string)$payload['router_identity']]);
        $router = $routerStmt->fetch();
        $provided = trim((string)($_SERVER['HTTP_X_PIXIEPOINT_KEY'] ?? ''));
        if (!$router || $provided === '' || !hash_equals((string)$router['api_key'], $provided)) {
            $this->json(['ok' => false, 'error' => 'unauthorized_router'], 401);
        }

        $eventKey = (string)$payload['event_key'];
        $duplicate = $this->db->prepare('SELECT id,points_awarded FROM router_login_events WHERE event_key=? LIMIT 1');
        $duplicate->execute([$eventKey]);
        if ($existing = $duplicate->fetch()) {
            $this->json(['ok' => true, 'duplicate' => true, 'event_id' => (int)$existing['id'], 'points_awarded' => (int)$existing['points_awarded']]);
        }

        $mac = client_mac((string)($payload['mac'] ?? ''));
        $clientIp = (string)($payload['client_ip'] ?? '');
        $deviceId = $userId = $voucherId = null;
        $amount = (int)$payload['amount_pesos'];
        $divisor = max(1, (int)($this->config['points_pesos_per_point'] ?? 5));
        $excludeAt = max(0, (int)($this->config['points_exclude_sales_at_or_above'] ?? 50));
        $earnedPoints = ($excludeAt > 0 && $amount >= $excludeAt) ? 0 : intdiv($amount, $divisor);

        $this->db->beginTransaction();
        try {
            if ($mac !== '') {
                $scope = 'router:' . (string)$payload['router_identity'] . '|interface:' . (string)($payload['interface_name'] ?? '');
                $device = $this->devices->resolve($mac, $scope, $clientIp) ?: [];
                $deviceId = $device['id'] ?? null;
                $userId = $device['user_id'] ?? null;
            }
            $voucherStmt = $this->db->prepare('SELECT id FROM vouchers WHERE code=? LIMIT 1');
            $voucherStmt->execute([(string)$payload['username']]);
            $voucherId = $voucherStmt->fetchColumn() ?: null;

            $awardedPoints = $userId ? $earnedPoints : 0;
            $stmt = $this->db->prepare('INSERT INTO router_login_events(event_key,router_id,device_id,user_id,voucher_id,username,mac,client_ip,interface_name,device_name,vendo_name,amount_pesos,duration_seconds,is_extension,points_earned,points_awarded) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$eventKey,$router['id'],$deviceId,$userId,$voucherId,(string)$payload['username'],$mac?:null,$clientIp?:null,$payload['interface_name']??null,$payload['device_name']??null,$payload['vendo_name']??null,$amount,(int)$payload['duration_seconds'],(int)$payload['is_extension'],$earnedPoints,$awardedPoints]);
            $eventId = (int)$this->db->lastInsertId();
            if ($userId && $awardedPoints > 0) {
                $this->db->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$awardedPoints, $userId]);
            }
            $this->db->prepare('UPDATE routers SET last_seen_at=? WHERE id=?')->execute([now(), $router['id']]);
            $this->db->commit();
        } catch (\Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $duplicate->execute([$eventKey]);
            if ($existing = $duplicate->fetch()) {
                $this->json(['ok' => true, 'duplicate' => true, 'event_id' => (int)$existing['id'], 'points_awarded' => (int)$existing['points_awarded']]);
            }
            $this->json(['ok' => false, 'error' => 'login_event_failed'], 500);
        }

        $this->json(['ok' => true, 'duplicate' => false, 'event_id' => $eventId, 'points_awarded' => $awardedPoints]);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
