<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Http\Request;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;

final class DashboardController
{
    public function __construct(private PDO $db, private AuthContext $auth, private View $view) {}

    public function index(Request $request): never
    {
        $user = $this->auth->requireAccount();
        $uid = (int)$user['id'];

        $deviceStmt = $this->db->prepare('SELECT COUNT(*) FROM devices WHERE user_id=?');
        $deviceStmt->execute([$uid]);
        $sessionStmt = $this->db->prepare('SELECT COUNT(*) FROM sessions WHERE user_id=?');
        $sessionStmt->execute([$uid]);

        $metrics = [
            'Points' => (int)$user['points'],
            'My devices' => (int)$deviceStmt->fetchColumn(),
            'My sessions' => (int)$sessionStmt->fetchColumn(),
        ];
        $owner = $this->auth->isPlatformOwner();
        if ($owner) {
            $metrics['Routers'] = (int)$this->db->query('SELECT COUNT(*) FROM routers WHERE enabled=1')->fetchColumn();
            $metrics['Active sessions'] = (int)$this->db->query("SELECT COUNT(*) FROM sessions WHERE status='active'")->fetchColumn();
            $metrics['Users'] = (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        }

        $cards = '';
        foreach ($metrics as $label => $value) {
            $cards .= '<div class="metric"><small>' . e($label) . '</small><strong>' . e($value) . '</strong></div>';
        }

        $recentStmt = $this->db->prepare('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id WHERE s.user_id=? ORDER BY s.updated_at DESC LIMIT 8');
        $recentStmt->execute([$uid]);
        $rows = '';
        foreach ($recentStmt->fetchAll() as $session) {
            $rows .= '<tr><td>' . e($session['username'] ?: '—') . '</td><td class="code">' . e($session['mac'] ?: '—') . '</td><td>' . e($session['router_name'] ?: '—') . '</td><td><span class="badge ' . ($session['status'] === 'active' ? '' : 'off') . '">' . e($session['status']) . '</span></td><td>' . e($session['updated_at']) . '</td></tr>';
        }

        $role = $owner ? 'Platform owner' : 'PixiePoint user';
        $ownerNote = $owner ? '<section class="panel"><h2>Platform management</h2><p class="muted">Manage the centralized MikroTik Hotspot service using the navigation above.</p></section>' : '';
        $content = '<div class="heading"><div><h1>My dashboard</h1><p class="muted">Welcome, ' . e($user['name']) . ' · ' . e($role) . '</p></div></div><section class="grid">' . $cards . '</section>' . $ownerNote . '<section class="panel"><h2>My recent Wi-Fi sessions</h2><table><thead><tr><th>Access</th><th>Device</th><th>Router</th><th>Status</th><th>Updated</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No account-linked sessions yet. Guest sessions continue to work normally.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Dashboard', $content, true, $owner);
    }
}
