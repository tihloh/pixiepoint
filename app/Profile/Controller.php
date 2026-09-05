<?php

declare(strict_types=1);

namespace PixiePoint\App\Profile;

use PixiePoint\App\Admin\Users\AvatarService;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\BusinessName;
use PixiePoint\App\Services\View;
use RuntimeException;
use Tihloh\Prefab\Users\Services\UserManager;

final class Controller
{
    public function __construct(
        private AuthContext $auth,
        private View $view,
        private UserManager $users,
        private AvatarService $avatars,
        private BusinessName $businessNames,
    ) {
    }

    public function index(): never
    {
        $actor = $this->auth->requireAccount();
        $userId = (int) $actor['id'];
        $message = (string) ($_SESSION['profile_flash'] ?? '');
        unset($_SESSION['profile_flash']);

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_csrf();

            try {
                $action = (string) ($_POST['action'] ?? 'save');

                if ($action === 'avatar') {
                    $avatarUrl = $this->avatars->store($userId, (string) ($_POST['avatar_data'] ?? ''));
                    $this->users->update($userId, ['avatar_url' => $avatarUrl], $this->context());
                } else {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                    $businessNameTemplate = trim((string) ($_POST['business_name_template'] ?? ''));

                    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('Enter a name and valid email address.');
                    }
                    if (mb_strlen($businessNameTemplate) > 255) {
                        throw new RuntimeException('Business name template is too long.');
                    }

                    $existing = $this->users->findByEmail($email);
                    if ($existing && (int) $existing->id !== $userId) {
                        throw new RuntimeException('That email address is already in use.');
                    }

                    $this->users->update($userId, [
                        'name' => $name,
                        'email' => $email,
                        'business_name_template' => $businessNameTemplate ?: null,
                    ], $this->context());
                }

                $_SESSION['profile_flash'] = '<div class="alert ok">Profile updated.</div>';
            } catch (\Throwable $e) {
                $_SESSION['profile_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/profile');
        }

        $user = $this->users->find($userId)?->toArray() ?? [];
        $content = $this->view->renderFile(__DIR__ . '/views/index.php', [
            'user' => $user,
            'businessName' => $this->businessNames->owner($userId),
            'message' => $message,
            'csrf' => csrf_token(),
        ]);

        $this->view->page(
            'Profile',
            $content,
            true,
            $this->auth->navigation(),
        );
    }

    private function context(): array
    {
        return [
            'actor_id' => $this->auth->auth()->id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }
}
