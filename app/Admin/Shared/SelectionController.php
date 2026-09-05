<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Shared;

use PDO;
use PixiePoint\App\Services\AuthContext;

final class SelectionController
{
    public function __construct(
        private PDO $db,
        private AuthContext $auth,
    ) {
    }

    public function router(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $routerId = max(0, (int) ($_GET['select'] ?? 0));

        if ($routerId < 1) {
            unset($_SESSION['pixiepoint_selected_router_id'], $_SESSION['pixiepoint_selected_vendo_id']);
            redirect($this->returnPath('/dashboard'));
        }

        $access = new RouterAccess($this->db);
        if (!$access->canView($routerId, $userId, $platformOwner)) {
            redirect('/admin/routers');
        }

        $stmt = $this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1 LIMIT 1');
        $stmt->execute([$routerId]);
        if (!$stmt->fetchColumn()) {
            redirect('/admin/routers');
        }

        $_SESSION['pixiepoint_selected_router_id'] = $routerId;
        unset($_SESSION['pixiepoint_selected_vendo_id']);

        redirect($this->returnPath('/admin/routers/' . $routerId));
    }

    public function vendo(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $routerId = max(0, (int) ($_SESSION['pixiepoint_selected_router_id'] ?? 0));
        $vendoId = max(0, (int) ($_GET['select'] ?? 0));

        if ($routerId < 1) {
            unset($_SESSION['pixiepoint_selected_vendo_id']);
            redirect('/admin/routers');
        }

        $access = new RouterAccess($this->db);
        if (!$access->canView($routerId, $userId, $platformOwner)) {
            unset($_SESSION['pixiepoint_selected_router_id'], $_SESSION['pixiepoint_selected_vendo_id']);
            redirect('/admin/routers');
        }

        if ($vendoId < 1) {
            unset($_SESSION['pixiepoint_selected_vendo_id']);
            redirect($this->returnPath('/admin/vendos'));
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM vendos WHERE id=? AND router_id=? AND enabled=1 LIMIT 1',
        );
        $stmt->execute([$vendoId, $routerId]);
        if (!$stmt->fetchColumn()) {
            unset($_SESSION['pixiepoint_selected_vendo_id']);
            redirect($this->returnPath('/admin/vendos'));
        }

        $_SESSION['pixiepoint_selected_vendo_id'] = $vendoId;

        redirect($this->returnPath('/admin/vendos'));
    }

    private function returnPath(string $fallback): string
    {
        $return = trim((string) ($_GET['return'] ?? ''));
        if ($return === '') {
            return $fallback;
        }

        $parts = parse_url($return);
        if (
            !$parts
            || isset($parts['scheme'])
            || isset($parts['host'])
            || !str_starts_with((string) ($parts['path'] ?? ''), '/')
        ) {
            return $fallback;
        }

        return $return;
    }
}
