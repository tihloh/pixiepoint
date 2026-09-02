<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use RuntimeException;
use Throwable;
use PixiePoint\App\Admin\Shared\FeatureController;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int)$user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $message = '';

        if ($this->isPost()) {
            require_csrf();
            $action = (string)($_POST['action'] ?? 'create');
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'router_id' => 'required|integer|min:1',
                'base_url' => 'trim|required|string|max:255',
                'server_ip' => 'trim|required|string|max:45',
                'interface_name' => 'trim|null_if_empty|nullable|string|max:128',
                'password_mode' => 'trim|required|string|max:32',
                'charging_enabled' => 'default:0|integer|min:0|max:1',
                'eload_enabled' => 'default:0|integer|min:0|max:1',
                'enabled' => 'default:0|integer|min:0|max:1',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                $url = parse_url((string)$data['base_url']);
                $scheme = strtolower((string)($url['scheme'] ?? ''));
                $host = trim((string)($url['host'] ?? ''));
                $serverIp = trim((string)$data['server_ip']);

                if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                    $message = '<div class="alert">Local base URL must be a complete http:// or https:// address.</div>';
                } elseif (filter_var($serverIp, FILTER_VALIDATE_IP) === false) {
                    $message = '<div class="alert">Server IP must be a valid IPv4 or IPv6 address.</div>';
                } elseif (!in_array((string)$data['password_mode'], ['blank', 'voucher'], true)) {
                    $message = '<div class="alert">Invalid password mode.</div>';
                } else {
                    try {
                        $routerCheck = $this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1');
                        $routerCheck->execute([(int)$data['router_id']]);
                        if (!$routerCheck->fetchColumn()) throw new RuntimeException('Router unavailable.');

                        if ($action === 'update') {
                            $id = max(0, (int)($_POST['id'] ?? 0));
                            if ($id < 1) throw new RuntimeException('Vendo not found.');
                            $sql = 'UPDATE vendos SET router_id=?,name=?,base_url=?,server_ip=?,interface_name=?,password_mode=?,charging_enabled=?,eload_enabled=?,enabled=? WHERE id=?';
                            $params = [
                                (int)$data['router_id'],
                                $data['name'],
                                rtrim((string)$data['base_url'], '/'),
                                $serverIp,
                                $data['interface_name'] ?? null,
                                $data['password_mode'],
                                (int)($data['charging_enabled'] ?? 0),
                                (int)($data['eload_enabled'] ?? 0),
                                (int)($data['enabled'] ?? 0),
                                $id,
                            ];
                            if (!$platformOwner) {
                                $sql .= ' AND owner_user_id=?';
                                $params[] = $userId;
                            }
                            $stmt = $this->db->prepare($sql);
                            $stmt->execute($params);
                            if ($stmt->rowCount() < 1) {
                                $exists = $this->db->prepare('SELECT id FROM vendos WHERE id=?' . ($platformOwner ? '' : ' AND owner_user_id=?'));
                                $exists->execute($platformOwner ? [$id] : [$id, $userId]);
                                if (!$exists->fetchColumn()) throw new RuntimeException('Vendo not found.');
                            }
                            $this->audit('vendo.updated', 'vendo', $id, 'PisoWiFi vendo was updated.', ['router_id' => (int)$data['router_id'], 'server_ip' => $serverIp]);
                            $message = '<div class="alert ok">Vendo updated.</div>';
                        } else {
                            $stmt = $this->db->prepare('INSERT INTO vendos(owner_user_id,router_id,name,base_url,server_ip,interface_name,password_mode,charging_enabled,eload_enabled) VALUES(?,?,?,?,?,?,?,?,?)');
                            $stmt->execute([
                                $userId,
                                (int)$data['router_id'],
                                $data['name'],
                                rtrim((string)$data['base_url'], '/'),
                                $serverIp,
                                $data['interface_name'] ?? null,
                                $data['password_mode'],
                                (int)($data['charging_enabled'] ?? 0),
                                (int)($data['eload_enabled'] ?? 0),
                            ]);
                            $id = (int)$this->db->lastInsertId();
                            $this->audit('vendo.created', 'vendo', $id, 'PisoWiFi vendo was registered.', ['router_id' => (int)$data['router_id'], 'server_ip' => $serverIp]);
                            $message = '<div class="alert ok">Vendo added.</div>';
                        }
                    } catch (Throwable $e) {
                        $message = '<div class="alert">The vendo could not be saved. ' . e($e->getMessage()) . '</div>';
                    }
                }
            }
        }

        $sql = 'SELECT v.*,r.name router_name,r.identity router_identity,u.email owner_email FROM vendos v JOIN routers r ON r.id=v.router_id LEFT JOIN users u ON u.id=v.owner_user_id';
        $params = [];
        if (!$platformOwner) {
            $sql .= ' WHERE v.owner_user_id=?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY v.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $this->page('Vendos', __DIR__ . '/views/index.php', [
            'message' => $message,
            'vendos' => $stmt->fetchAll(),
            'routers' => $this->db->query('SELECT id,name,identity FROM routers WHERE enabled=1 ORDER BY name')->fetchAll(),
            'canManageVendos' => $this->auth->can('vendos.manage'),
            'isPlatformOwner' => $platformOwner,
            'csrf' => csrf_token(),
        ]);
    }
}
