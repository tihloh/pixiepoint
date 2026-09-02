<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\Input\Input;

final class TeamController extends FeatureController
{
    public function index(int|string $id): never
    {
        $routerId = max(0, (int) $id);
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);

        if ($routerId < 1 || !$access->canView($routerId, $userId, $platformOwner)) {
            http_response_code(404);
            $this->page('Router team', __DIR__ . '/views/team.php', [
                'router' => null,
                'members' => [],
                'message' => '<div class="alert">Router not found.</div>',
                'canManageTeam' => false,
                'currentRole' => null,
                'csrf' => csrf_token(),
            ]);
        }

        $stmt = $this->db->prepare('SELECT id,name,identity FROM routers WHERE id=? LIMIT 1');
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();

        if (!$router) {
            http_response_code(404);
            $this->page('Router team', __DIR__ . '/views/team.php', [
                'router' => null,
                'members' => [],
                'message' => '<div class="alert">Router not found.</div>',
                'canManageTeam' => false,
                'currentRole' => null,
                'csrf' => csrf_token(),
            ]);
        }

        $currentRole = $access->roleFor($routerId, $userId);
        $canManageTeam = $access->canManageTeam($routerId, $userId, $platformOwner)
            && $this->auth->can('routers.manage');

        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();

            if (!$canManageTeam) {
                $message = '<div class="alert">You cannot manage this router team.</div>';
            } else {
                $action = (string) ($_POST['action'] ?? 'save');

                try {
                    if ($action === 'remove') {
                        $memberId = max(0, (int) ($_POST['user_id'] ?? 0));
                        $this->assertCanChangeMember(
                            $access,
                            $routerId,
                            $memberId,
                            $currentRole,
                            $platformOwner,
                        );
                        $access->removeMember($routerId, $memberId);
                        $this->audit(
                            'router.team.member.removed',
                            'router',
                            $routerId,
                            'Router team member was removed.',
                            ['user_id' => $memberId],
                        );
                        $message = '<div class="alert ok">Team member removed.</div>';
                    } else {
                        $result = Input::fromRequest()->process([
                            'email' => 'trim|required|email|max:254',
                            'role' => 'trim|required|string|max:32',
                        ]);

                        if ($result->fails()) {
                            throw new RuntimeException(strip_tags($this->errors($result->errors())));
                        }

                        $data = $result->validated();
                        $role = (string) $data['role'];
                        $allowedRoles = $platformOwner || $currentRole === 'owner'
                            ? ['owner', 'manager', 'operator', 'viewer']
                            : ['manager', 'operator', 'viewer'];

                        if (!in_array($role, $allowedRoles, true)) {
                            throw new RuntimeException('You cannot assign that role.');
                        }

                        $userStmt = $this->db->prepare(
                            'SELECT id,name,email FROM users WHERE LOWER(email)=LOWER(?) AND active=1 LIMIT 1',
                        );
                        $userStmt->execute([(string) $data['email']]);
                        $member = $userStmt->fetch();

                        if (!$member) {
                            throw new RuntimeException('No active PixiePoint account uses that email address.');
                        }

                        $memberId = (int) $member['id'];
                        $this->assertCanChangeMember(
                            $access,
                            $routerId,
                            $memberId,
                            $currentRole,
                            $platformOwner,
                        );
                        $access->setMember($routerId, $memberId, $role);

                        $this->audit(
                            'router.team.member.saved',
                            'router',
                            $routerId,
                            'Router team member was added or updated.',
                            [
                                'user_id' => $memberId,
                                'role' => $role,
                            ],
                        );
                        $message = '<div class="alert ok">Team member saved.</div>';
                    }
                } catch (Throwable $e) {
                    $message = '<div class="alert">' . e($e->getMessage()) . '</div>';
                }
            }

            $_SESSION['admin_flash'] = $message;
            redirect('/admin/routers/' . $routerId . '/team');
        }

        $this->page('Router team', __DIR__ . '/views/team.php', [
            'router' => $router,
            'members' => $access->members($routerId),
            'message' => $message,
            'canManageTeam' => $canManageTeam,
            'currentRole' => $currentRole,
            'isPlatformOwner' => $platformOwner,
            'csrf' => csrf_token(),
        ]);
    }

    private function assertCanChangeMember(
        RouterAccess $access,
        int $routerId,
        int $memberId,
        ?string $currentRole,
        bool $platformOwner,
    ): void {
        if ($memberId < 1) {
            throw new RuntimeException('Team member not found.');
        }

        if ($platformOwner || $currentRole === 'owner') {
            return;
        }

        if ($access->roleFor($routerId, $memberId) === 'owner') {
            throw new RuntimeException('Only a router owner can change another owner.');
        }
    }
}
