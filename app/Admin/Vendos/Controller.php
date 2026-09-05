<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use PixiePoint\App\Services\BusinessName;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);
        $businessNames = new BusinessName($this->db);

        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        $selectedRouterId = max(0, (int) ($_SESSION['pixiepoint_selected_router_id'] ?? 0));
        if ($selectedRouterId < 1) {
            redirect('/admin/routers');
        }

        if (!$access->canView($selectedRouterId, $userId, $platformOwner)) {
            unset($_SESSION['pixiepoint_selected_router_id']);
            redirect('/admin/routers');
        }

        if ($this->isPost()) {
            require_csrf();
            $action = (string) ($_POST['action'] ?? 'create');

            if ($action === 'toggle_debug') {
                $message = $this->toggleDebug($access, $userId, $platformOwner, $selectedRouterId);
            } else {
                $message = $this->saveVendo($access, $userId, $platformOwner, $action, $selectedRouterId);
            }

            $_SESSION['admin_flash'] = $message;
            redirect('/admin/vendos');
        }

        $stmt = $this->db->prepare(
            'SELECT v.*,r.name router_name,r.identity router_identity
             FROM vendos v
             JOIN routers r ON r.id=v.router_id
             WHERE v.router_id=?
             ORDER BY v.created_at DESC',
        );
        $stmt->execute([$selectedRouterId]);
        $vendos = $stmt->fetchAll();
        foreach ($vendos as &$vendo) {
            $vendo['business_name'] = $businessNames->vendo((int) $vendo['id']);
        }
        unset($vendo);

        $routers = $this->db->query(
            'SELECT id,name,identity FROM routers WHERE enabled=1 AND id=' . $selectedRouterId,
        )->fetchAll();

        $routerBusinessName = $businessNames->router($selectedRouterId);

        $this->page('Vendos', __DIR__ . '/views/index.php', [
            'message' => $message,
            'vendos' => $vendos,
            'routers' => $routers,
            'routerBusinessName' => $routerBusinessName,
            'canManageVendos' => $this->auth->can('vendos.manage'),
            'isPlatformOwner' => $platformOwner,
            'csrf' => csrf_token(),
        ]);
    }

    private function toggleDebug(
        RouterAccess $access,
        int $userId,
        bool $platformOwner,
        int $selectedRouterId,
    ): string {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $enabled = (string) ($_POST['debug_enabled'] ?? '0') === '1';

        $vendo = $this->findVendo($id);
        if (
            !$vendo
            || (int) $vendo['router_id'] !== $selectedRouterId
            || !$access->canManage((int) $vendo['router_id'], $userId, $platformOwner)
        ) {
            return '<div class="alert">Vendo not found or access denied.</div>';
        }

        $stmt = $this->db->prepare('UPDATE vendos SET debug_enabled=? WHERE id=?');
        $stmt->execute([$enabled ? 1 : 0, $id]);

        $this->audit(
            'vendo.debug.' . ($enabled ? 'enabled' : 'disabled'),
            'vendo',
            $id,
            'Hotspot debug was ' . ($enabled ? 'enabled' : 'disabled') . ' for this vendo.',
        );

        return '<div class="alert ok">Debug '
            . ($enabled ? 'enabled' : 'disabled')
            . ' for '
            . e($vendo['name'])
            . '.</div>';
    }

    private function saveVendo(
        RouterAccess $access,
        int $userId,
        bool $platformOwner,
        string $action,
        int $selectedRouterId,
    ): string {
        $result = Input::fromRequest()->process([
            'name' => 'trim|required|string|max:160',
            'business_name_template' => 'trim|null_if_empty|nullable|string|max:255',
            'router_id' => 'required|integer|min:1',
            'base_url' => 'trim|required|string|max:255',
            'server_ip' => 'trim|required|string|max:45',
            'client_subnet' => 'trim|null_if_empty|nullable|string|max:64',
            'interface_name' => 'trim|null_if_empty|nullable|string|max:128',
            'password_mode' => 'trim|required|string|max:32',
            'charging_enabled' => 'default:0|integer|min:0|max:1',
            'eload_enabled' => 'default:0|integer|min:0|max:1',
            'enabled' => 'default:0|integer|min:0|max:1',
        ]);

        if ($result->fails()) {
            return $this->errors($result->errors());
        }

        $data = $result->validated();
        $routerId = (int) $data['router_id'];
        $serverIp = trim((string) $data['server_ip']);
        $subnet = trim((string) ($data['client_subnet'] ?? ''));
        $baseUrl = $this->normalizeBaseUrl((string) $data['base_url']);

        if ($routerId !== $selectedRouterId) {
            return '<div class="alert">Select the router before managing its vendos.</div>';
        }
        if ($baseUrl === null) {
            return '<div class="alert">Vendo address must be a valid IP address, hostname, or http:// / https:// URL.</div>';
        }
        if (filter_var($serverIp, FILTER_VALIDATE_IP) === false) {
            return '<div class="alert">Server IP must be a valid IPv4 or IPv6 address.</div>';
        }
        if ($subnet !== '' && !$this->validCidr($subnet)) {
            return '<div class="alert">Client subnet must be valid CIDR, for example 10.0.3.0/24.</div>';
        }
        if (!in_array((string) $data['password_mode'], ['blank', 'voucher'], true)) {
            return '<div class="alert">Invalid password mode.</div>';
        }
        if (!$access->canManage($routerId, $userId, $platformOwner)) {
            return '<div class="alert">You do not have access to manage that router.</div>';
        }

        try {
            $routerCheck = $this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1');
            $routerCheck->execute([$routerId]);
            if (!$routerCheck->fetchColumn()) {
                throw new RuntimeException('Router unavailable.');
            }

            if ($action === 'update') {
                $id = max(0, (int) ($_POST['id'] ?? 0));
                $existing = $this->findVendo($id);
                if (
                    !$existing
                    || (int) $existing['router_id'] !== $selectedRouterId
                    || !$access->canManage((int) $existing['router_id'], $userId, $platformOwner)
                ) {
                    throw new RuntimeException('Vendo not found or access denied.');
                }

                $stmt = $this->db->prepare(
                    'UPDATE vendos
                     SET router_id=?,name=?,business_name_template=?,base_url=?,server_ip=?,client_subnet=?,interface_name=?,password_mode=?,charging_enabled=?,eload_enabled=?,enabled=?
                     WHERE id=?',
                );
                $stmt->execute([
                    $routerId,
                    $data['name'],
                    $data['business_name_template'] ?? null,
                    $baseUrl,
                    $serverIp,
                    $subnet ?: null,
                    $data['interface_name'] ?? null,
                    $data['password_mode'],
                    (int) ($data['charging_enabled'] ?? 0),
                    (int) ($data['eload_enabled'] ?? 0),
                    (int) ($data['enabled'] ?? 0),
                    $id,
                ]);

                $this->audit(
                    'vendo.updated',
                    'vendo',
                    $id,
                    'PisoWiFi vendo was updated.',
                    ['router_id' => $routerId, 'server_ip' => $serverIp],
                );

                return '<div class="alert ok">Vendo updated.</div>';
            }

            $stmt = $this->db->prepare(
                'INSERT INTO vendos(owner_user_id,router_id,name,business_name_template,base_url,server_ip,client_subnet,interface_name,password_mode,charging_enabled,eload_enabled)
                 VALUES(NULL,?,?,?,?,?,?,?,?,?,?)',
            );
            $stmt->execute([
                $routerId,
                $data['name'],
                $data['business_name_template'] ?? null,
                $baseUrl,
                $serverIp,
                $subnet ?: null,
                $data['interface_name'] ?? null,
                $data['password_mode'],
                (int) ($data['charging_enabled'] ?? 0),
                (int) ($data['eload_enabled'] ?? 0),
            ]);

            $id = (int) $this->db->lastInsertId();
            $this->audit(
                'vendo.created',
                'vendo',
                $id,
                'PisoWiFi vendo was registered.',
                ['router_id' => $routerId, 'server_ip' => $serverIp],
            );

            return '<div class="alert ok">Vendo added.</div>';
        } catch (Throwable $e) {
            return '<div class="alert">The vendo could not be saved. '
                . e($e->getMessage())
                . '</div>';
        }
    }

    private function findVendo(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id,router_id,name FROM vendos WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $vendo = $stmt->fetch();

        return $vendo ?: null;
    }

    private function normalizeBaseUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('~^https?://~i', $value)) {
            $value = 'http://' . $value;
        }

        $parts = parse_url($value);
        if (
            !$parts
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
        ) {
            return null;
        }

        return rtrim($value, '/');
    }

    private function validCidr(string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, '');
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }

        $bits = strlen($bin) * 8;

        return filter_var(
            $prefix,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => $bits]],
        ) !== false;
    }
}
